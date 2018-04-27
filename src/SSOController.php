<?php

namespace tw88\flarumsso;

use Flarum\Http\Rememberer;
use Flarum\Forum\UrlGenerator;
use Flarum\Foundation\Application;
use Flarum\Http\SessionAuthenticator;
use Flarum\Core\Repository\UserRepository;
use Zend\Diactoros\Response\RedirectResponse;
use Flarum\Http\Controller\ControllerInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;

class SSOController implements ControllerInterface
{
    protected $app;
    protected $events;
    protected $settings;
    protected $url;
    protected $users;
    protected $authenticator;
    protected $rememberer;

    public function __construct(
        Application $app,
        EventsDispatcher $events,
        SettingsRepositoryInterface $settings,
        UrlGenerator $url,
        UserRepository $users,
        SessionAuthenticator $authenticator,
        Rememberer $rememberer
    ) {
        $this->app = $app;
        $this->events = $events;
        $this->settings = $settings;
        $this->url = $url;
        $this->users = $users;
        $this->authenticator = $authenticator;
        $this->rememberer = $rememberer;
    }

    /**
     * @param Request $request
     * @param array $routeParams
     * @return \Psr\Http\Message\ResponseInterface|RedirectResponse
     */
    public function handle(Request $request, array $routeParams = [])
    {
        return $this->handleSsoLogin($request);
    }

    public function handleSsoLogin(Request $request)
    {
        var_dump('here');
        die();
    }
}
