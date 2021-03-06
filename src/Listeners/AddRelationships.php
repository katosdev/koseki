<?php
/*
 * This file is part of reflar/koseki.
 *
 * Copyright (c) ReFlar.
 *
 * http://reflar.io
 *
 * For the full copyright and license information, please view the license.md
 * file that was distributed with this source code.
 */

namespace Reflar\Koseki\Listeners;

use Flarum\Api\Event\Serializing;
use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Api\Serializer\TagSerializer;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;

class AddRelationships
{
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen(Serializing::class, [$this, 'prepareApiAttributes']);
    }

    /**
     * @param Serializing $event
     */
    public function prepareApiAttributes(Serializing $event)
    {
        if ($event->isSerializer(TagSerializer::class)) {
            $lastDiscussion = $event->model->lastDiscussion;
            $user = isset($lastDiscussion->last_user_id) ? User::find($lastDiscussion->last_user_id) : null;

            $event->attributes['hasChild'] = $event->model->where('parent_id', $event->model->id)->count() >= 1 ? true : false;
            $event->attributes['discussionsCount'] = count($event->model->discussions);
            $event->attributes['commentsCount'] = max($event->model->discussions->max('comments_count') - 1, 0);
            $event->attributes['icon'] = $event->model->icon;

            if ($user) {
                $groups = $user->groups()->get()->all();

                $event->attributes['lastUser'] = [
                    'username'  => $user->username,
                    'avatarUrl' => $user->avatarUrl,
                    'color'     => isset($groups[0]) ? $groups[0]['color'] : '',
                ];
            }
        }

        if ($event->isSerializer(ForumSerializer::class)) {
            $lastUser = User::orderBy('joined_at', 'DESC')->limit(1)->first();

            $event->attributes['discussionsCount'] = Discussion::all()->count();
            $event->attributes['postsCount'] = Post::all()->count();
            $event->attributes['usersCount'] = User::all()->count();
            $event->attributes['lastUser'] = $lastUser->username;
            $event->attributes['kosekiTagsView'] = $this->settings->get('koseki.tags_view');
            $event->attributes['kosekiStatistics'] = $this->settings->get('koseki.statistics_widget');
        }
    }
}
