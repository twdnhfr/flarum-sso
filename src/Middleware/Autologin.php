<?php

namespace tw88\sso\Middleware;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use Flarum\Tags\Tag;
use Flarum\Core\User;
use Flarum\Foundation\Application;
use Flarum\Http\SessionAuthenticator;
use Zend\Stratigility\MiddlewareInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
                    $klinikKuerzel = strtolower($user['fall']['klinik']['kuerzel']);
                    $dbuser        = User::where('uniqid', $user['uniqid'])->first();

                    if (null == $dbuser) {
                        // Find User via Email if there is no matching UUID
                        $dbuser = User::where('email', $user['email'])->first();
                    }

                    $baseTag = Tag::where('slug', 'like', "%$klinikKuerzel%")->first();

                    $this->authenticator->logIn($session, $dbuser->id);

                    if ($baseTag) {
                        header('Location: /t/' . $baseTag->slug);
                        exit;
                    }

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
