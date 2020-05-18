<?php

namespace tw88\sso\Middleware;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use Flarum\Tags\Tag;
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

        $this->dotenv = new Dotenv($_SERVER['DOCUMENT_ROOT'] . '/..');
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
            if (is_array($user)) {
                $klinikKuerzel = strtolower($user['fall']['klinik']['kuerzel'] ?? '');
                $dbuser        = User::where('uniqid', $user['uniqid'])->first();

                if (null == $dbuser) {
                    // Find User via Email if there is no matching UUID
                    $dbuser = User::where('email', $user['email'])->first();
                }

                $baseTag = Tag::where('slug', 'like', "%$klinikKuerzel%")->first();

                $this->authenticator->logIn($session, $dbuser->id);

                if ($baseTag) {
                    return new RedirectResponse('/t/' . $baseTag->slug);
                }

                return new RedirectResponse('/');
            }
        } elseif ($actor->isGuest() == false && null == $user && 'admin' !== $actor->username) {
            $this->authenticator->logOut($session);

            return new RedirectResponse('/');
        }

        return $handler->handle($request);
    }
}
