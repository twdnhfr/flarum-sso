<?php
namespace tw88\sso\Middleware;

use Dotenv\Dotenv;
use Flarum\Foundation\Application;
use Flarum\Http\AccessToken;
use Flarum\Http\SessionAuthenticator;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use tw88\sso\SSO;
use Zend\Stratigility\MiddlewareInterface;

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
    public function __invoke(Request $request, Response $response, callable $out = null)
    {
        do {
            // Check if a guest.
            $actor = $request->getAttribute('actor');
            if ($actor->isGuest()) {

                $user = $this->sso->getUserInfo();

                if (is_array($user)) {
                    $session = $request->getAttribute('session');
                    $this->authenticator->logIn($session, 1);

                    // Generate remember me token (3600 is the time Flarum uses).
                    $token = AccessToken::generate(1, 3600);
                    $token->save();

                    if ($this->events && $token && $user) {
                        // Trigger the login event.
                        $this->events->fire(new UserLoggedIn($user, $token));

                        // Return the redirect response.
                        return $response;
                    }
                }

                break;
            }

        } while (false);

        return $out ? $out($request, $response) : $response;
    }
}
