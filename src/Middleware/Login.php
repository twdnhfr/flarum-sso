<?php

namespace tw88\sso\Middleware;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use Flarum\User\User;
use Flarum\Foundation\Application;
use Flarum\Http\SessionAuthenticator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Flarum\Settings\SettingsRepositoryInterface;

class Login implements MiddlewareInterface
{
    protected $app;
    protected $settings;
    protected $authenticator;
    protected $sso;
    protected $dotenv;

    public function __construct(Application $app, SettingsRepositoryInterface $settings, SessionAuthenticator $authenticator)
    {
        $this->app           = $app;
        $this->settings      = $settings;
        $this->authenticator = $authenticator;

        $this->dotenv = new Dotenv($_SERVER['DOCUMENT_ROOT']);
        $this->dotenv->load();
        $this->dotenv->required(['SSO_URL', 'SSO_BROKER', 'SSO_SECRET']);

        $this->sso = new SSO(getenv('SSO_URL'), getenv('SSO_BROKER'), getenv('SSO_SECRET'));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() === '/login') {
            $credentials = $request->getParsedBody();
            $session     = $request->getAttribute('session');

            try {
                $this->sso->login($credentials['identification'], $credentials['password']);
            } catch (\Exception $ex) {
                if (in_array($ex->getMessage(), ['Invalid credentials', 'user not found'])) {
                    return new EmptyResponse(401);
                }

                throw $ex;
            }

            $ssoUser = $this->sso->getUserInfo();

            if (is_array($ssoUser)) {
                $uniqUser = User::where('uniqid', $ssoUser['uniqid'])->first();

                $user = $uniqUser ? $uniqUser : User::where('email', $ssoUser['email'])->first();

                if (!$user) {
                    do {
                        $randomUserName = 'Nutzer' . rand(1000, 99999);
                    } while (User::where(['username' => $randomUserName])->first());

                    $user = User::register($randomUserName, $ssoUser['email'], '');
                    $user->activate();
                }

                if (null === $user->uniqid) {
                    $user->uniqid = $ssoUser['uniqid'];
                    $user->save();
                }

                $this->authenticator->logIn($session, $user->id);

                return new EmptyResponse(200);
            }
        }

        return $handler->handle($request);
    }
}
