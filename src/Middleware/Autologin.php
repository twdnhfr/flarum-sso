<?php
namespace tw88\sso\Middleware;

use Dotenv\Dotenv;
use Flarum\Core\User;
use Flarum\Foundation\Application;
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
            $actor   = $request->getAttribute('actor');
            $user    = $this->sso->getUserInfo();
            $session = $request->getAttribute('session');

            if ($actor->isGuest()) {
                if (is_array($user)) {

                    $dbuser = User::where('uniqid', $user['uniqid'])->first();

                    $this->authenticator->logIn($session, $dbuser->id);

                    header('Location: /');
                    exit;
                }
            } elseif ($actor->isGuest() == false && null == $user) {
                $this->authenticator->logOut($session);

                header('Location: /');
                exit;
            }

        } while (false);

        return $out ? $out($request, $response) : $response;
    }
}
