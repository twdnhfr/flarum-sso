<?php

namespace tw88\sso\Listener;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use Flarum\Core\User;
use Flarum\Event\UserLoggedOut;
use Illuminate\Events\Dispatcher;
use Flarum\Foundation\Application;
use tw88\sso\Middleware\Autologin;
use Flarum\Event\CheckUserPassword;
use Flarum\Event\ConfigureMiddleware;

class AddConfigureMiddleware
{
    /**
     * @var Application
     */
    protected $app;
    protected $authenticator;

    /**
     * @param Application $app
     */
    public function __construct(Application $app, SessionAuthenticator $authenticator)
    {
        $this->app = $app;
        $this->authenticator = $authenticator;
    }

    /**
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        $events->listen(CheckUserPassword::class, [$this, 'whenCheckUserPassword']);
        $events->listen(ConfigureMiddleware::class, [$this, 'whenConfigureMiddleware']);
        $events->listen(UserLoggedOut::class, [$this, 'whenUserLoggedOut']);
    }

    public function whenCheckUserPassword(CheckUserPassword $event)
    {
        $sso = new SSO(getenv('SSO_URL'), getenv('SSO_BROKER'), getenv('SSO_SECRET'));

        $sso->login($event->user->email, $event->password);

        $user    = $sso->getUserInfo();

        if (is_array($user)) {
            $dbuser = User::where('uniqid', $user['uniqid'])->first();

            if (null == $dbuser) {
                // Find User via Email if there is no matching UUID
                $dbuser = User::where('email', $user['email'])->first();
            }

            return true;

            // $this->authenticator->logIn($event->user->session, $dbuser->id);
        }
    }

    /**
     * @param ConfigureMiddleware $event
     */
    public function whenConfigureMiddleware(ConfigureMiddleware $event)
    {
        $event->pipe->pipe($event->path, $this->app->make(Autologin::class));
    }

    /**
     * @param UserLoggedOut $event
     */
    public function whenUserLoggedOut(UserLoggedOut $event)
    {
        $this->dotenv = new Dotenv($_SERVER['DOCUMENT_ROOT']);
        $this->dotenv->load();
        $this->dotenv->required(['SSO_URL', 'SSO_BROKER', 'SSO_SECRET']);

        $this->sso = new SSO(getenv('SSO_URL'), getenv('SSO_BROKER'), getenv('SSO_SECRET'));

        $this->sso->logout();
    }
}
