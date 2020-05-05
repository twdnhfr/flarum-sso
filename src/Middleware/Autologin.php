<?php

namespace tw88\sso\Middleware;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use Flarum\User\User;
use Flarum\Foundation\Application;
use Flarum\Http\SessionAuthenticator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\RedirectResponse;
use Flarum\Settings\SettingsRepositoryInterface;

class Autologin implements MiddlewareInterface
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var SessionAuthenticator
     */
    protected $authenticator;

    /**
     * @var Sso Broker
     */
    protected $sso;

    protected $dotenv;

    /**
     * @param Application $app
     * @param SettingsRepositoryInterface $settings
     */
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

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if a guest.
        $actor   = $request->getAttribute('actor');
        $user    = $this->sso->getUserInfo();
        $session = $request->getAttribute('session');

        if ($actor->isGuest()) {
            $ssoUser = $this->sso->getUserInfo();

            if (is_array($ssoUser)) {
                $uniqUser = User::where('uniqid', $ssoUser['uniqid'])->first();

                $user = $uniqUser ? $uniqUser : User::where('email', $ssoUser['email'])->first();

                if (!$user) {
                    $counter = 0;

                    do {
                        $counter++;
                        $randomUserName = $ssoUser['vorname'] . $counter;
                    } while (User::where(['username' => $randomUserName])->first());

                    $user = User::register($randomUserName, $ssoUser['email'], '');
                    $user->activate();
                }

                if (null === $user->uniqid) {
                    $user->uniqid = $ssoUser['uniqid'];
                    $user->save();
                }

                $this->authenticator->logIn($session, $user->id);

                return new RedirectResponse("/");
            }
        } elseif ($actor->isGuest() == false && null == $user) {
            $this->authenticator->logOut($session);

            return new RedirectResponse('/');
        }

        return $handler->handle($request);
    }
}
