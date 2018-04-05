<?php

namespace SoloPVP;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;

class GameStartWait extends PluginTask{

    public function __construct(Plugin $owner){
        parent::__construct($owner);
        $this->plugin = $owner;
    }

    public function onRun(int $currentTick){
        $waitSeconds = $this->getOwner()->getConfig()->get("WaitInterval");
        if ($this->getOwner()->getConfig()->get("MinNumOfPeople") <= count($this->getOwner()->participatingPlayers)) {
            $this->getOwner()->cancelAllTasks();
            $handler = $this->getOwner()->tasks["gameWillStartInFiveSeconds"] = $this->getOwner()->getServer()->getScheduler()->scheduleDelayedTask($this->getOwner()->tasks["GameWillStartInFiveSeconds"], 20 * $waitSeconds);
            $this->getOwner()->taskIDs[] = $handler->getTaskId();
        } else {
            $this->getOwner()->getLogger()->info("Scheduler >> 参加人数不足。{$waitSeconds}秒後に再試行...");
            $this->getOwner()->cancelAllTasks();
            $handler = $this->getOwner()->tasks["gameStartWait"] = $this->getOwner()->getServer()->getScheduler()->scheduleDelayedTask($this->getOwner()->tasks["GameStartWait"], 20 * 15);
            $this->getOwner()->taskIDs[] = $handler->getTaskId();
        }
    }

    public function cancel(){
        $this->getHandler()->cancel();
    }
}