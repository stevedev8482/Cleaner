<?php

declare(strict_types=1);

namespace cleaner;

use cleaner\utils\TimeUtils;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class Cleaner extends PluginBase
{

    protected float $restart = 0;
    protected float $clean = 0;

    public function onEnable(): void
    {
        $this->saveResource('config.yml'); # save config
        $this->setCleanData();
        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            public function __construct(
                private readonly Cleaner $cleaner
            ){}

            public function onRun(): void
            {
                $this->cleaner->updateTime();
            }
        }, 20);
    }

    private function setCleanData(): void
    {
        $this->restart = $this->getConfig()->get("restart");
        $this->clean = $this->getConfig()->get("clean");
        $this->restart = TimeUtils::minToSec($this->restart) - 1;
        $this->clean = TimeUtils::minToSec($this->clean) - 1;
    }

    public function formatRestartTime(): string
    {
        $seconds = (int) $this->restart;
        return $this->extracted($seconds);
    }

    public function formatCleanTime(): string
    {
        $seconds = (int) $this->clean;
        return $this->extracted($seconds);
    }

    public function updateTime(): void
    {
        $this->restart--;
        $this->clean--;

        $players = Server::getInstance()->getOnlinePlayers();
        foreach ($players as $player) {
            $this->updatePlayerTags($player);
        }

        if ($this->restart === 300) { // 60 * 5
            Server::getInstance()->broadcastMessage($this->getConfig()->get('to-restart'));
        }

        if ($this->restart < 0) {
            $this->kickPlayers($players);
            return;
        }

        if ($this->clean < 0) {
            $this->clean = TimeUtils::minToSec($this->getConfig()->get('clean'));
            $this->cleanEntities();
        }
    }

    private function updatePlayerTags($player): void
    {
        (new PlayerTagUpdateEvent($player, new ScoreTag("scorehud.restart", $this->formatRestartTime())))->call();
        (new PlayerTagUpdateEvent($player, new ScoreTag("scorehud.clean", $this->formatCleanTime())))->call();
    }

    private function kickPlayers(array $players): void
    {
        foreach ($players as $player) {
            $player->kick($this->getConfig()->get('restart-message'), $this->getConfig()->get('restart-message'));
        }
        $this->setCleanData();
    }

    private function cleanEntities(): void
    {
        foreach (Server::getInstance()->getWorldManager()->getDefaultWorld()->getEntities() as $entity) {
            if (!$entity instanceof Player) {
                $entity->kill();
            }
        }
        Server::getInstance()->broadcastMessage($this->getConfig()->get('clean-message'));
    }

    /**
     * @param int $seconds
     * @return string
     */
    public function extracted(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return $hours . "ч " . $minutes . "м " . $seconds . "с";
        } elseif ($minutes > 0) {
            return $minutes . "м " . $seconds . "c";
        } else {
            return $seconds . "c";
        }
    }
}