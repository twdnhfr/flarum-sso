<?php

namespace tw88\sso\Listener;

use Flarum\Group\Group;
use tw88\sso\Model\Discussion;
use Illuminate\Events\Dispatcher;
use Flarum\Foundation\Application;
use Illuminate\Support\Facades\URL;
use Illuminate\Contracts\Mail\Mailer;
use Flarum\Post\Event\Posted as PostWasPosted;
use Flarum\Settings\SettingsRepositoryInterface;

class MailNotificator
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;
    protected $app;
    protected $mailer;

    public function __construct(Application $app, Mailer $mailer, SettingsRepositoryInterface $settings)
    {
        $this->app      = $app;
        $this->mailer   = $mailer;
        $this->settings = $settings;
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen(PostWasPosted::class, [$this, 'postWasPosted']);
    }

    public function postWasPosted(PostWasPosted $event)
    {
        $recipients = [];
        $subject    = 'Neue Aktivität im Dr. Becker Patienten-Forum';

        $post           = $event->post;
        $discussion     = Discussion::all()->where('id', '=', $post->discussion_id)->first();
        $lastPostedUser = $discussion->lastPostedUser;

        $newDiscussion = 0 === $discussion->comment_count;

        $linkToDiscussion = $this->app->url('d/' . $discussion->id . '-' . $discussion->slug);

        if ($newDiscussion) {
            $content = "Es wurde eine neue Diskussion erstellt";
        } else {
            $content = "Es wurde ein Kommentar zur einer Diskussion verfasst";
        }

        foreach ($discussion->tags as $tag) {
            if (null === $tag->moderator_role_id) {
                continue;
            }

            $moderatorGroup = Group::all()->where('id', '=', $tag->moderator_role_id)->first();
            foreach ($moderatorGroup->users as $user) {
                if ($newDiscussion) {
                    if ($discussion->user_id === $user->id) {
                        continue;
                    }
                } else if (null !== $lastPostedUser && $lastPostedUser->id === $user->id) {
                    continue;
                }

                $recipients[] = $user->email;
            }
        }

        $content .= "<br/>Klicke <a href='$linkToDiscussion'>hier</a> um direkt zur Diskussion zu gelangen.";

        $this->mailer->send([], [], function ($message) use ($recipients, $subject, $content) {
            $message->to($recipients)
                    ->subject($subject)
                    ->setBody($content, 'text/html');
        });
    }
}
