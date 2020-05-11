<?php
namespace tw88\sso\Model;

use Flarum\Discussion\Discussion as OriginalDiscussion;
use Flarum\Tags\Tag;

class Discussion extends OriginalDiscussion {
    public function tags() {
        return $this->belongsToMany(Tag::class);
    }
}