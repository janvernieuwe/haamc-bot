<?php

namespace App\Message;

use App\Channel\Channel;
use App\Exception\InvalidChannelException;
use App\Util\Util;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\TextChannel;
use Jikan\Model\Anime\Anime;

/**
 * Class JoinableChannelMessage
 *
 * @package App\Message
 */
class JoinableChannelMessage
{
    public const CHANNEL_REGXP = '/(c=)(\d+)/';
    public const TEXT_MESSAGE = '';
    public const AUTHOR_IMG_URL = 'https://i.imgur.com/pcdrHvS.png';

    /**
     * @var \CharlotteDunois\Yasmin\Models\Message
     */
    private $message;

    /**
     * JoinableChannelMessage constructor.
     *
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     */
    public function __construct(\CharlotteDunois\Yasmin\Models\Message $message)
    {
        $this->message = $message;
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    public static function isJoinableChannel(string $content): bool
    {
        return preg_match(self::CHANNEL_REGXP, $content);
    }

    /**
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     *
     * @return bool
     */
    public static function isJoinChannelMessage(\CharlotteDunois\Yasmin\Models\Message $message): bool
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $message = new self($message);

        return $message->getFieldValue('kijkers') !== null;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getFieldValue(string $key)
    {
        if (!count($this->message->embeds)) {
            return null;
        }
        $data = array_filter(
            $this->message->embeds[0]->fields,
            function (array $data) use ($key) {
                return $data['name'] === $key;
            }
        );
        if (!count($data)) {
            return null;
        }
        $data = array_values($data);

        return $data[0]['value'];
    }

    /**
     * @return int|null
     */
    public function getAnimeId(): ?int
    {
        if (preg_match('#https?://myanimelist.net/anime/(\d+)#', $this->getAnimeLink(), $channel)) {
            return (int)$channel[1];
        }

        return null;
    }

    /**
     * @return string
     */
    public function getAnimeLink(): ?string
    {
        return $this->message->embeds[0]->url;
    }

    /**
     * @param Anime       $anime
     * @param GuildMember $member
     *
     * @throws InvalidChannelException
     */
    public function addUser(Anime $anime, GuildMember $member): void
    {
        // No double joins
        if (Channel::hasAccess($this->message, $member->id)) {
            return;
        }
        // Join channel
        $channel = Channel::getTextChannel($this->message);
        $channel->overwritePermissions(
            $member->id,
            Channel::ROLE_VIEW_MESSAGES,
            0,
            'User joined the channel'
        );
        // Update the member counf
        $channel = Channel::getTextChannel($this->message);
        $count = Channel::getUserCount($channel) + 1;
        $this->updateWatchers($anime, $channel->id, $count);
        // Announce join
        $channel->send(
            sprintf(
                ':inbox_tray:  %s kijkt nu mee naar %s',
                Util::mention((int)$member->id),
                Util::channelLink((int)$channel->id)
            )
        );
    }

    /**
     * @return TextChannel
     * @throws InvalidChannelException
     */
    public function getChannelFromMessage(): TextChannel
    {
        $channel = $this->message->guild->channels->get($this->getChannelId());
        if ($channel === null) {
            throw new InvalidChannelException('Channel not found');
        }

        return $channel;
    }

    /**
     * @return int|null
     */
    public function getChannelId(): ?int
    {
        if (preg_match(self::CHANNEL_REGXP, $this->getAnimeLink(), $channel)) {
            return (int)$channel[2];
        }

        return null;
    }

    /**
     * @param TextChannel $channel
     *
     * @return int
     */
    public function getSubsciberCount(TextChannel $channel): int
    {
        return Channel::getUserCount($channel);
    }

    /**
     * @param Anime $anime
     * @param int   $channelId
     * @param int   $subs
     */
    public function updateWatchers(Anime $anime, int $channelId, int $subs = 0): void
    {
        $embed = self::generateRichChannelMessage($anime, $channelId, $this->getEmbeddedAnimeLink(), $subs);
        $this->message->edit(self::TEXT_MESSAGE, $embed);
    }

    /**
     * @return string
     */
    public function getEmbeddedAnimeLink(): string
    {
        return $this->message->embeds[0]->url;
    }

    /**
     * @param Anime  $anime
     * @param int    $channelId
     * @param string $link
     * @param int    $subs
     *
     * @return array
     */
    public static function generateRichChannelMessage(Anime $anime, int $channelId, string $link, int $subs = 0): array
    {
        return [
            'embed' => [
                'author'    => [
                    'name'     => $anime->getTitle(),
                    'icon_url' => self::AUTHOR_IMG_URL,
                    'url'      => $link,
                ],
                'url'       => $link,
                'thumbnail' => ['url' => $anime->getImageUrl()],
                'footer'    => [
                    'text' => 'Druk op de reactions om te joinen / leaven',
                ],
                'fields'    => [
                    [
                        'name'   => 'datum',
                        'value'  => (string)$anime->getAired(),
                        'inline' => true,
                    ],
                    [
                        'name'   => 'genres',
                        'value'  => implode(', ', $anime->getGenres()),
                        'inline' => true,
                    ],
                    [
                        'name'   => 'channel',
                        'value'  => Util::channelLink($channelId),
                        'inline' => true,
                    ],
                    [
                        'name'   => 'kijkers',
                        'value'  => (string)$subs,
                        'inline' => true,
                    ],
                ],
            ],
        ];
    }/** @noinspection MoreThanThreeArgumentsInspection */

    /**
     * @return string
     */
    public function getAnimeTitle(): string
    {
        return $this->message->embeds[0]->title ?? $this->message->embeds[0]->author['name'];
    }

    /**
     * @return string
     */
    public function getAnimeImageUrl(): string
    {
        return $this->message->embeds[0]->thumbnail['url'];
    }

    /**
     * @param Anime       $anime
     * @param GuildMember $member
     *
     * @throws InvalidChannelException
     */
    public function removeUser(Anime $anime, GuildMember $member): void
    {
        // No double joins
        if (!Channel::hasAccess($this->message, $member->id)) {
            return;
        }
        // Remove member
        $channel = Channel::getTextChannel($this->message);
        $channel->overwritePermissions(
            $member->id,
            0,
            Channel::ROLE_VIEW_MESSAGES,
            'User left the channel'
        );
        // Update member count
        $count = Channel::getUserCount($channel) - 1;
        $this->updateWatchers($anime, $channel->id, $count);
        // Announce leave
        $channel->send(
            sprintf(
                ':outbox_tray: %s kijkt nu niet meer mee naar %s',
                Util::mention((int)$member->id),
                Util::channelLink($this->getChannelId())
            )
        );
    }

    /**
     * @return int
     */
    public function getWatchers(): int
    {
        return (int)$this->getFieldValue('kijkers');
    }
}
