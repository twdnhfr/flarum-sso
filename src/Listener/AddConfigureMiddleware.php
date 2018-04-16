<?php
namespace tw88\sso\Listener;

use Dotenv\Dotenv;
use Flarum\Event\ConfigureMiddleware;
use Flarum\Event\UserLoggedOut;
use Flarum\Foundation\Application;
use Illuminate\Contracts\Events\Dispatcher;
use tw88\sso\Middleware\Autologin;
use tw88\sso\SSO;

class AddConfigureMiddleware
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param Dispatcher $events
     */
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
