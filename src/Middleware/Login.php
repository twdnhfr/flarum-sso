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
use Zend\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Flarum\Settings\SettingsRepositoryInterface;

class Login implements MiddlewareInterface
{
    protected $app;
    protected $settings;
    protected $authenticator;
    protected $sso;
    protected $dotenv;

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

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() === '/login') {
            $credentials = $request->getParsedBody();
            $session     = $request->getAttribute('session');

            if ('admin' === $credentials['identification']) {
                return $handler->handle($request);
            }

            try {
                $this->sso->login($credentials['identification'], $credentials['password']);
            } catch (\Exception $ex) {
                if (in_array($ex->getMessage(), ['Invalid credentials', 'user not found'])) {
                    return new EmptyResponse(401);
                }

                throw $ex;
            }

            $ssoUser = $this->sso->getUserInfo();

            $klinikName = $ssoUser['fall']['klinik']['name'];

            $group = Group::all()->where('name_singular', '=', "$klinikName-Pat")->first();

            if (!$group) {
                $group = Group::build("$klinikName-Pat", "$klinikName-Pats", null, null);
                $group->save();
            }

            $moderatorGroup = Group::all()->where('name_singular', '=', "$klinikName-Mod")->first();

            if (!$moderatorGroup) {
                $moderatorGroup = Group::build("$klinikName-Mod", "$klinikName-Mods", null, null);
                $moderatorGroup->save();
            }

            $tag = Tag::all()->where('name', '=', $klinikName)->first();

            if (!$tag) {
                $tag                    = Tag::build($klinikName, str_slug($klinikName), null, null, null, false);
                $tag->position          = 1;
                $tag->moderator_role_id = $moderatorGroup->id;
                $tag->save();
            }

            if (is_array($ssoUser)) {
                $uniqUser = User::where('uniqid', $ssoUser['uniqid'])->first();
                $user     = $uniqUser ? $uniqUser : User::where('email', $ssoUser['email'])->first();

                if (!$user) {
                    $counter = 0;

                    do {
                        $counter++;
                        $randomUserName = $ssoUser['vorname'] . $counter;
                    } while (User::where(['username' => $randomUserName])->first());

                    $user = User::register($randomUserName, $ssoUser['email'], '');
                    $user->activate();
                }

                if (null === $user->uniqid) {
                    $user->uniqid = $ssoUser['uniqid'];
                    $user->save();
                    $user->groups()->attach($group->id);
                }

                $hasCorrectGroup = false;
                foreach ($user->groups as $grp) {
                    if ("$klinikName-Pat" == $grp->name_singular) {
                        $hasCorrectGroup = true;
                    }
                }

                if (!$hasCorrectGroup) {
                    $user->groups()->attach($group->id);
                }

                $this->authenticator->logIn($session, $user->id);

                return new EmptyResponse(200);
            }
        }

        return $handler->handle($request);
    }
}
