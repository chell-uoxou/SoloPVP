<?php
/**
 * Created by PhpStorm.
 * User: chell_uoxou
 * Date: 2018/04/01
 * Time: 午後 10:02
 */

namespace SoloPVP;

use pocketmine\block\Block;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\PluginTask;

class RevivalWorld extends PluginTask
{
    public $isRestoring = false;

    public function __construct(Plugin $owner){
        parent::__construct($owner);
        $this->plugin = $owner;
    }

    public function onRun(int $currentTick){

        $done = false;
        $worldName = $this->getOwner()->getConfig()->get("StartPoint_1")["world"];

        if ($this->getOwner()->r_count < count($this->getOwner()->setBlocks)){
            $defaultBlock = $this->getOwner()->setBlocks[$this->getOwner()->r_count][0]; //defaultBlock
            $pos = $this->getOwner()->setBlocks[$this->getOwner()->r_count][1]; //pos
            $this->getOwner()->getServer()->getLevelByName($worldName)->setBlock($pos, $defaultBlock);
            $done = true;
        }

        if ($this->getOwner()->r_count < count($this->getOwner()->brokenBlocks)) {
            $block = $this->getOwner()->brokenBlocks[$this->getOwner()->r_count];
            $this->getOwner()->getServer()->getLevelByName($worldName)->setBlock($block[1], $block[0]);
            $done = true;
        }
        $this->isRestoring = $done;
        if ($done){
            $this->getOwner()->r_count++;
            $handler = $this->getOwner()->getServer()->getScheduler()->scheduleDelayedTask($this, 1);
            $this->taskIDs[] = $handler->getTaskId();
        }else{
            $this->getOwner()->getLogger()->info("World restoration complete.({$this->getOwner()->r_count} blocks)");
        }
    }

    public function isRestoring(){
        return $this->isRestoring;
    }

    public function cancel(){
        $this->cancel();
    }
}