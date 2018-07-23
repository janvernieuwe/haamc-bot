<?php

namespace App\Channel;

use App\Exception\CharacterNotFoundException;
use App\Message\CotsNomination;
use CharlotteDunois\Yasmin\Models\Message;
use Jikan\MyAnimeList\MalClient;
use Jikan\Request\Anime\AnimeRequest;
use Jikan\Request\Character\CharacterRequest;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class SongOfTheWeek
 *
 * @package App\Discord
 */
class CotsChannel extends Channel
{
    /**
     * @var int
     */
    private $roleId;

    /**
     * @var MalClient
     */
    private $mal;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var int
     */
    private $channelId;

    /**
     * CotsChannel constructor.
     *
     * @param MalClient $mal
     * @param ValidatorInterface           $validator
     * @param int                          $cotsChannelId
     * @param int                          $roleId
     */
    public function __construct(
        MalClient $mal,
        ValidatorInterface $validator,
        int $cotsChannelId,
        int $roleId
    ) {
        $this->roleId = $roleId;
        $this->mal = $mal;
        $this->validator = $validator;
        $this->channelId = $cotsChannelId;
    }

    /**
     * @param Message $message
     *
     * @return CotsNomination
     * @throws CharacterNotFoundException
     */
    public function loadNomination(Message $message): CotsNomination
    {
        if (!CotsNomination::isNomination($message->content)) {
            throw new CharacterNotFoundException('Invalid message '.$message->content);
        }

        $anime = $this->mal->getAnime(new AnimeRequest(CotsNomination::getAnimeId($message->content)));
        $character = $this->mal->getCharacter(new CharacterRequest(CotsNomination::getCharacterId($message->content)));

        return CotsNomination::fromYasmin($message, $character, $anime);
    }

    public function closeNominations()
    {
        $this->deny($this->roleId, Channel::ROLE_SEND_MESSAGES);
        $this->message('Er kan nu enkel nog gestemd worden op de nominaties :checkered_flag:');
    }

    /**
     * @param string $season
     */
    public function openChannel(string $season)
    {
        $this->allow($this->roleId, Channel::ROLE_SEND_MESSAGES);
        $this->message(sprintf('Bij deze zijn de nominaties voor season  %s geopend!', $season));
    }

    /**
     * @param CotsNomination $nomination
     * @param string         $season
     */
    public function announceWinner(CotsNomination $nomination, string $season)
    {
        $this->deny($this->roleId, Channel::ROLE_SEND_MESSAGES);
        $this->message(
            sprintf(
                ":trophy: Het character van %s is **%s**! van **%s**\n"
                ."Genomineerd door %s\nhttps://myanimelist.net/character/%s",
                $season,
                $nomination->getCharacter()->name,
                $nomination->getAnime()->title,
                $nomination->getAuthor(),
                $nomination->getCharacter()->mal_id
            )
        );
        $this->getLastNominations();
    }

    /**
     * @param int $limit
     *
     * @return CotsNomination[]
     * @throws \Exception
     */
    public function getLastNominations(int $limit = 25): array
    {
        $messages = $this->getManyMessages(35);
        $contenders = [];
        foreach ($messages as $message) {
            if (preg_match('/Bij deze zijn de nominaties voor/', $message['content'])) {
                break;
            }
            if (preg_match('/Het character van/', $message['content'])) {
                break;
            }
            if (CotsNomination::isNomination($message['content'])) {
                $anime = $this->mal->loadAnime(CotsNomination::getAnimeId($message['content']));
                $character = $this->mal->loadCharacter(CotsNomination::getCharacterId($message['content']));
                $nomination = new CotsNomination($message, $character, $anime);
                $contenders[] = $nomination;
            }
        }
        $contenders = $this->sortByVotes($contenders);
        $contenders = \array_slice($contenders, 0, $limit);

        return $contenders;
    }

    /**
     * @return string
     */
    public function getTop10(): string
    {
        $output = ['De huidige Character of the season ranking is'];
        foreach ($this->getLastNominations(10) as $i => $nomination) {
            $voiceActors = $nomination->getCharacter()->voice_actor;
            $output[] = sprintf(
                ":mens: %s) **%s**, *%s*\nvotes: **%s** | door: *%s* | voice actor: *%s* | score: %s",
                $i + 1,
                $nomination->getCharacter()->name,
                $nomination->getAnime()->title,
                $nomination->getVotes(),
                $nomination->getAuthor(),
                count($voiceActors) ? $nomination->getCharacter()->voice_actor[0]['name'] : 'n/a',
                $nomination->getAnime()->score
            );
        }

        return implode(PHP_EOL, $output);
    }
}
