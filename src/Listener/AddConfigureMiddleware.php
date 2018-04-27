<?php

namespace tw88\sso\Listener;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use tw88\sso\Middleware\Login;
use Flarum\Event\UserLoggedOut;
use Illuminate\Events\Dispatcher;
use tw88\flarumsso\SSOController;
use Flarum\Foundation\Application;
use tw88\sso\Middleware\Autologin;
use Flarum\Event\ConfigureMiddleware;
use Flarum\Event\ConfigureForumRoutes;
use Flarum\Core\Validator\UserValidator;

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

    public function configureForumRoutes(ConfigureForumRoutes $event)
    {
        $actions = [
            'auth.sso.login' => '/login',
        ];

        foreach ($actions as $k => $v) {
            $event->post($v, $k, SSOController::class);
        }
    }

    /**
     * @param ConfigureMiddleware $event
     */
    public function whenConfigureMiddleware(ConfigureMiddleware $event)
    {
        $event->pipe->pipe($event->path, $this->app->make(Login::class));
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
