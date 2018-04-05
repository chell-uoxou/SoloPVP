<?php
/**
 * Created by PhpStorm.
 * User: chell_uoxou
 * Date: 2018/04/01
 * Time: 午前 12:48
 */

namespace SoloPVP;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;

class OnTickedSecond extends PluginTask{

    public function __construct(Plugin $owner){
        parent::__construct($owner);
        $this->plugin = $owner;
    }

    public function onRun(int $currentTick){
        if ($this->getOwner()->isPlaying(1)) {
            if (DO_SEND_BOSSBAR == true) {
                $this->getOwner()->sendBossBar();
            }
            foreach ($this->getOwner()->participatingPlayers as $player) {
                if ($player instanceof Player) {
                    $team = $this->getOwner()->getTeamDisplayName($this->getOwner()->getTeamIdFromPlayer($player));
                    $kill = $this->getOwner()->playersData[$player->getName()]["kill"];
                    $death = $this->getOwner()->playersData[$player->getName()]["death"];
                    $teamPoint = $this->getOwner()->getTeamPoint($this->getOwner()->getTeamIdFromPlayer($player));
                    $current = $this->getOwner()->gameRemainingSeconds;
                    $minutes = floor(($current / 60) % 60);
                    $seconds = $current % 60;
                    $hms = sprintf("%02d:%02d", $minutes, $seconds);
                    $tipText = $this->getOwner()->getMessage("status-text", $player->getLocale(), [$team, $kill, $death, $teamPoint, $hms]);
                    $player->sendPopup($tipText);
                }
            }
            $this->getOwner()->gameRemainingSeconds--;
        }
    }

    public function cancel(){
        $this->getHandler()->cancel();
    }
}