<?php

namespace tw88\sso\Listener;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use Flarum\User\UserValidator;
use tw88\sso\Middleware\Login;
use Illuminate\Events\Dispatcher;
use Flarum\Foundation\Application;
use tw88\sso\Middleware\Autologin;
use Flarum\Event\ConfigureMiddleware;
use Flarum\User\Event\LoggedOut as UserLoggedOut;

class AddConfigureMiddleware extends UserValidator
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen(ConfigureMiddleware::class, [$this, 'whenConfigureMiddleware']);
        $events->listen(UserLoggedOut::class, [$this, 'whenUserLoggedOut']);
    }

    /**
     * @param ConfigureMiddleware $event
     */
    public function whenConfigureMiddleware(ConfigureMiddleware $event)
    {
        $event->pipe->pipe($this->app->make(Login::class));
        $event->pipe->pipe($this->app->make(Autologin::class));
    }

    /**
     * @param UserLoggedOut $event
     */
    public function whenUserLoggedOut(UserLoggedOut $event)
    {
        if (null === $event->user->uniqid) {
            return;
        }

        // users without an uniqid are flarum-only accounts like an admin account

        $this->dotenv = new Dotenv($_SERVER['DOCUMENT_ROOT'] . '/..');
        $this->dotenv->load();
        $this->dotenv->required(['SSO_URL', 'SSO_BROKER', 'SSO_SECRET']);

        $this->sso = new SSO(getenv('SSO_URL'), getenv('SSO_BROKER'), getenv('SSO_SECRET'));

        try {
            $this->sso->logout();
        } catch (\Exception $ex) {
            if (!starts_with($ex->getMessage(), 'Expected application/json')) {
                throw $ex;
            }
        }
    }
}
