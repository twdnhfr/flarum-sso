<?php

namespace tw88\sso\Middleware;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use Flarum\Core\User;
use Flarum\Foundation\Application;
use Flarum\Http\SessionAuthenticator;
use Zend\Stratigility\MiddlewareInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Psr\Http\Message\ServerRequestInterface as Request;

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

    public function __invoke(Request $request, Response $response, callable $out = null)
    {
        if ($request->getUri()->getPath() === '/login') {
            $credentials = $request->getParsedBody();
            $session = $request->getAttribute('session');

            $this->sso->login($credentials['identification'], $credentials['password']);

            $ssoUser = $this->sso->getUserInfo();

            if (is_array($ssoUser)) {
                $uniqUser = User::where('uniqid', $ssoUser['uniqid'])->first();

                $user = $uniqUser ? $uniqUser : User::where('email', $ssoUser['email'])->first();

                if ($user) {
                    if (is_null($user->uniqid)) {
                        $user->uniqid = $ssoUser['uniqid'];
                        $user->save();
                    }

                    $user = User::where('uniqid', $ssoUser['uniqid'])->first();

                    $this->authenticator->logIn($session, $user);
                }

                if (! $user) {
                    do {
                        $randomUserName = 'Nutzer' . rand(1000, 99999);
                    } while (User::where(['username' => $randomUserName])->first());

                    $newUser = User::register($randomUserName, $ssoUser['email'], '');
                    $newUser->activate();
                    $newUser->uniqid = $ssoUser['uniqid'];
                    $newUser->save();
                }

                return new RedirectResponse('/');
            }
        }

        return $out ? $out($request, $response) : $response;
    }
}
