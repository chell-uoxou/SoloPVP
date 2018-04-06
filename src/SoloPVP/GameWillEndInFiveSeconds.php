<?php
/**
 * Created by PhpStorm.
 * User: chell_uoxou
 * Date: 2018/04/01
 * Time: 午前 12:41
 */

namespace SoloPVP;

use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\PopSound;
use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;

class GameWillEndInFiveSeconds extends PluginTask{

    public function __construct(Plugin $owner){
        parent::__construct($owner);
        $this->plugin = $owner;
    }

    public function onRun(int $currentTick){
        if ($this->getOwner()->e_countDownSeconds <= 0) {
            $this->getOwner()->e_countDownSeconds = 5;
            foreach ($this->getOwner()->participatingPlayers as $player) {
                $pos = $player->getPosition();
                $level = $pos->getLevel();
                $sound = new AnvilFallSound($pos);
                $level->addSound($sound);
            }
            $this->getOwner()->end();
        } else {
            foreach ($this->getOwner()->participatingPlayers as $player) {
                if ($player instanceof Player) {
                    $player->sendPopup($this->getOwner()->e_countDownSeconds);
                    $pos = $player->getPosition();
                    $level = $pos->getLevel();
                    $sound = new PopSound($pos);
                    $level->addSound($sound);
                }
            }
            $this->getOwner()->getLogger()->info("Scheduler >> {$this->getOwner()->e_countDownSeconds}");
            $this->getOwner()->e_countDownSeconds--;
            $handler = $this->getOwner()->getServer()->getScheduler()->scheduleDelayedTask($this->getOwner()->tasks["GameWillEndInFiveSeconds"], 20);
            $this->getOwner()->taskIDs[] = $handler->getTaskId();
        }
    }

    public function cancel(){
        $this->cancel();
    }
}