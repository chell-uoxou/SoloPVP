<?php

namespace SoloPVP;

use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\PopSound;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;

class GameWillStartInFiveSeconds extends PluginTask{

    public function __construct(Plugin $owner){
        parent::__construct($owner);
        $this->plugin = $owner;
    }

    public function onRun(int $currentTick){
        if ($this->getOwner()->s_countDownSeconds <= 0) {
            $this->getOwner()->s_countDownSeconds = 5;
            foreach ($this->getOwner()->participatingPlayers as $player) {
                $pos = $player->getPosition();
                $level = $pos->getLevel();
                $sound = new AnvilFallSound($pos);
                $level->addSound($sound);
            }
            $this->getOwner()->gameStart();
        } else {
            foreach ($this->getOwner()->participatingPlayers as $player) {
                if ($player instanceof Player) {
                    $player->sendPopup($this->getOwner()->s_countDownSeconds);
                    $pos = $player->getPosition();
                    $level = $pos->getLevel();
                    $sound = new PopSound($pos);
                    $level->addSound($sound);
                }
            }
            $this->getOwner()->getLogger()->info("Scheduler >> {$this->getOwner()->s_countDownSeconds}");
            $this->getOwner()->s_countDownSeconds--;
            $handler = $this->getOwner()->getServer()->getScheduler()->scheduleDelayedTask($this->getOwner()->tasks["GameWillStartInFiveSeconds"], 20);
            $this->getOwner()->taskIDs[] = $handler->getTaskId();
        }
    }

    public function cancel(){
        $this->cancel();
    }
}