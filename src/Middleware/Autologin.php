<?php

namespace tw88\sso\Middleware;

use tw88\sso\SSO;
use Dotenv\Dotenv;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Flarum\Group\Group;
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
        $ssoUser = $this->sso->getUserInfo();
        $session = $request->getAttribute('session');

        if ($actor->isGuest()) {
            if (is_array($ssoUser)) {
                $klinikName    = $ssoUser['fall']['klinik']['name'];
                $klinikKuerzel = strtolower($ssoUser['fall']['klinik']['kuerzel'] ?? '');

                $group = Group::all()->where('name_singular', '=', "$klinikName-Pat")->first();

                if (!$group && "" != $klinikName) {
                    $group = Group::build("$klinikName-Pat", "$klinikName-Pats", null, null);
                    $group->save();
                }

                $forumUser = User::where('uniqid', $ssoUser['uniqid'])->first();

                if (null == $forumUser) {
                    // Find User via Email if there is no matching UUID
                    $forumUser = User::where('email', $ssoUser['email'])->first();
                }

                if (null == $forumUser) {
                    $counter = 0;

                    do {
                        $counter++;
                        $randomUserName = $ssoUser['vorname'] . $counter;
                    } while (User::where(['username' => $randomUserName])->first());

                    $forumUser = User::register($randomUserName, $ssoUser['email'], '');
                    $forumUser->activate();
                }

                if (null === $forumUser->uniqid) {
                    $forumUser->uniqid = $ssoUser['uniqid'];
                    $forumUser->save();
                }

                $baseTag = Tag::where('slug', 'like', "%$klinikKuerzel%")->first();

                $hasCorrectGroup = false;

                foreach ($forumUser->groups as $grp) {
                    if (1 === $grp->id) {
                        // Is Admin
                        $hasCorrectGroup = true;
                    } else if ("$klinikName-Mod" == $grp->name_singular) {
                        // Is Moderator
                        $hasCorrectGroup = true;
                    } else if ("$klinikName-Pat" == $grp->name_singular) {
                        $hasCorrectGroup = true;
                    }
                }

                if (!$hasCorrectGroup) {
                    $forumUser->groups()->attach($group->id);
                }

                $this->authenticator->logIn($session, $forumUser->id);

                if ($baseTag) {
                    return new RedirectResponse('/t/' . $baseTag->slug);
                }

                return new RedirectResponse('/');
            }
        } elseif ($actor->isGuest() == false && null == $ssoUser && 'admin' !== $actor->username) {
            $this->authenticator->logOut($session);

            return new RedirectResponse('/');
        }

        return $handler->handle($request);
    }
}
