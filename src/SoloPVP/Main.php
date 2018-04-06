<?php
/**
 * Created by PhpStorm.
 * User: chell_uoxou
 * Date: 2017/05/24
 * Time: 午後 4:03
 */

namespace SoloPVP;


use pocketmine\block\Block;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerChatEvent;

use pocketmine\utils\Config;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\item\Item;

use pocketmine\level\Position;
use pocketmine\level\sound\PopSound;

use pocketmine\math\Vector3;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;

use BossBarAPI\API;

class Main extends PluginBase implements Listener
{
    public $config;
    public $playersStat;

    public $playersData;
    public $messages;

    public $participatingPlayers = array();

    public $prifks = "§a[§dcSoloPVP§a]§f";

    public $s_countDownSeconds = 5; //don't change it!
    public $e_countDownSeconds = 5; //don't change it!

    public $gameRemainingSeconds;

    public $isPlaying;

    public $brokenBlocks;
    public $setBlocks;

    public $eid = null;
    public $r_count = 0;
    public $taskIDs = array();

    public $tasks = array(
        "GameWillStartInFiveSeconds" => null,
        "GameStartWait" => null,
        "GameWillEndInFiveSeconds" => null,
        "RevivalWorld" => null
    );

    const DO_SEND_BOSSBAR = false;
    const DO_RESET_INVENTORY = false;


    function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $messages[] = ("§7Created by §dchell_uoxou §8(@chell_uoxou).");
        $messages[] = ("§c§l二次配布は厳禁です！");
        $messages[] = ("§7アップデートの確認はこちらから：§8https://github.com/chell-uoxou/SoloPVP");

        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder(), 0744, true);
            $message[] = ("[NOTICE]§aデータ保管用のフォルダを作成しました。");
        }
        $this->saveDefaultConfig();
        $this->saveResource("messages.yml", false);
        $this->saveResource("players.json", false);
        $this->reloadConfig();
        $messages_handle = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $this->messages = $messages_handle->getAll();
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->playersStat = new Config($this->getDataFolder() . "players.json", Config::JSON);
        $getPrifks = $this->config->get("Prifks");
        $this->prifks = "§a[§d{$getPrifks}§a]§f ";
        $messages[] = ("[STATUS]プラグインメッセージ接頭語：" . $this->prifks);
        $messages[] = ("[STATUS]試合時間：§e" . $this->config->get("Interval") . "秒");
        $messages[] = ("[STATUS]試合開始確認間隔：§e" . $this->config->get("Interval") . "秒");
        $messages[] = ("[STATUS]必要最小人数：§e" . $this->config->get("MinNumOfPeople") . "人");
        $messages[] = ("[STATUS]参加可能最大人数：§e" . $this->config->get("MaxNumOfPeople") . "人");

        $this->tasks["GameStartWait"] = new GameStartWait($this);
        $this->tasks["GameWillStartInFiveSeconds"] = new GameWillStartInFiveSeconds($this);
        $this->tasks["GameWillEndInFiveSeconds"] = new GameWillEndInFiveSeconds($this);
        $this->tasks["OnTickedSecond"] = new OnTickedSecond($this);
        $this->tasks["RevivalWorld"] = new RevivalWorld($this);

        $this->organizeArrays();
        $this->tasks["GameStartWait"]->onRun($this->getServer()->getTick());

        $this->getServer()->getScheduler()->scheduleRepeatingTask($this->tasks["OnTickedSecond"], 20);

        if ($this->config->get("DoSendBossBer") == true) {
            if (!$this->getServer()->getPluginManager()->getPlugin("BossBarAPI")) {
                $messages[] = ("§6[WARNING]BossBarAPIプラグインが見つかりませんでした。");
                $messages[] = ("[STATUS]ウィザーバーの表示：§c無効");
                define('SoloPVP\DO_SEND_BOSSBAR', false);
            } else {
                define('SoloPVP\DO_SEND_BOSSBAR', true);
                $messages[] = ("[STATUS]ウィザーバーの表示；§a有効");
                $messages[] = ("[NOTICE]§6ウィザーバーのシステムは実験的なものです。");
                $messages[] = ("[NOTICE]§6不具合が生じた場合はconfig.ymlのDoSendBossBerの値をfalseに変更してください。");
            }
        } else {
            define('SoloPVP\DO_SEND_BOSSBAR', false);
            $messages[] = ("[STATUS]ウィザーバーの表示：§c無効");
        }

        if ($this->config->get("pos1")["x"] === null or $this->config->get("pos2")["x"] === null) {
            $messages[] = ("[NOTICE]§l§cランダムスポーン範囲頂点が未設定です。");
            $messages[] = ("[NOTICE]§6/spvp edit <pos1|pos2> にて、必ず設定してください。");
        }

        if ($this->config->get("DoResetInventory") == true) {
            $messages[] = ("[STATUS]インベントリリセット：§c有効");
            $messages[] = ("[NOTICE]§l§cワールドに入室したサバイバルモードのプレイヤーの持ち物がリセットされます。");
        } else {
            $messages[] = ("[STATUS]インベントリリセット：§c無効");
        }

        if ($this->config->get("DoResetInventory") == true) {
            $messages[] = ("[STATUS]インベントリリセット：§c有効");
            $messages[] = ("[NOTICE]§l§cワールドに入室したサバイバルモードのプレイヤーの持ち物がリセットされます。");
        } else {
            $messages[] = ("[STATUS]インベントリリセット：§c無効");
        }

        if ($this->getConfig()->get("pos1")["world"] != $this->getConfig()->get("pos2")["world"]) {
            $this->getLogger()->error("範囲頂点は同じワールドに設定してください。");
        }

        $longerLength = 0;
        foreach ($messages as $message) {
            $length = strlen(preg_replace('/§./', "", $message)) - (strlen(preg_replace('/§./', "", $message)) - mb_strlen(preg_replace('/§./', "", $message))) / 2;
            if ($longerLength < $length) {
                $longerLength = $length;
            }
        }

        $first_line_length = strlen("=====: " . $this->getFullName() . " :=====");
        $this->getLogger()->info("+ §l=====:§a " . $this->getFullName() . " §f:=====" . str_repeat("=", $longerLength - $first_line_length) . " +");
        foreach ($messages as $message) {
            $spaces = $longerLength - strlen(preg_replace('/§./', "", $message)) + (strlen(preg_replace('/§./', "", $message)) - mb_strlen(preg_replace('/§./', "", $message))) / 2 + 1;
            $this->getLogger()->info("| " . $message . str_repeat(" ", $spaces) . "§f|");
        }
        $this->getLogger()->info("+ " . str_repeat("=", $longerLength) . " +");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if (isset($args[0])) {
            switch (strtolower($args[0])) {
                case "start":
                    if (!$sender->isOp()) {
                        $this->sendContainedMessage($sender, "error-dont-have-permission");
                    } else {
                        if ($this->getConfig()->get("MinNumOfPeople") <= count($this->participatingPlayers)) {
                            if (!$sender instanceof Player) {
                                $this->sendStatus("", $sender);
                            }
                            $this->sendContainedMessage($sender, "game-started-1");
                            $this->gameStart();
                        } else {
                            $this->sendContainedMessage($sender, "error-gamestart-few-people");
                        }
                    }
                    return true;
                    break;

                case "end":
                    if (!$sender->isOp()) {
                        $this->sendContainedMessage($sender, "error-dont-have-permission");
                    } else {
                        if ($this->isPlaying()) {
                            $this->end();
                        } else {
                            $this->sendContainedMessage($sender, "error-there-arent-games");
                        }
                        return true;
                    }
                    return true;
                    break;

                case "edit":
                    if (!$sender->isOp()) {
                        $this->sendContainedMessage($sender, "error-dont-have-permission");
                    } else {
                        $this->editSettings($args, $sender);
                        return true;
                    }
                    return true;
                    break;

                case "add":
                    if (isset($args[1])) {
                        if ($this->isOnlinePlayer($args[1])) {
                            $player = $this->getServer()->getPlayer($args[1]);
                            $playerName = $args[1];
                            if (!isset($this->participatingPlayers[$playerName])) {
                                $isAdded = $this->add($player);
                                if ($isAdded) {
                                    if ($this->isPlaying()) {
                                        $this->joinGame($player);
                                    } else {
                                        $this->sendContainedMessage($player, "admin-made-join-game");
                                        $this->sendContainedMessage($player, "wait-for-starting");
                                        $this->sendContainedMessage($sender, "you-made-join-game", [$playerName]);
                                    }
                                } else {
                                    $player->sendMessage("§cSomething went wrong while adding player to game.");
                                }
                            } else {
                                $this->sendContainedMessage($player, "you-already-joined");
                            }
                        } else {
                            $sender->sendMessage($this->prifks . "§c{$args[1]}というプレーヤーは見つかりませんでした。");
                        }
                    } else {
                        $sender->sendMessage($this->prifks . "Usage: /spvp add <player name>");
                    }
                    return true;
                    break;

                case "status":
                    if (!$sender->isOp()) {
                        if ($this->getConfig()->get("AllowStatusCommand")) {
                            $this->sendStatus($args, $sender);
                        } else {
                            $this->sendContainedMessage($sender, "error-dont-have-permission");
                        }
                    } else {
                        $this->sendStatus($args, $sender);
                    }
                    return true;
                    break;

                case "join":
                    if ($sender instanceof Player) {
                        if (!isset($this->participatingPlayers[$sender->getName()])) {
                            if ($this->add($sender)) {
                                $this->joinGame($sender);
                                $playerName = $sender->getName();
                                $this->sendMessageInGame("0-joined-to-1", [$playerName]);
                            } else {
                                $this->sendContainedMessage($sender, "wait-for-starting");
                            }
                        } else {
                            $this->sendContainedMessage($sender, "you-already-joined");
                            $this->sendContainedMessage($sender, "wait-for-starting");
                        }
                    } else {
                        $sender->sendMessage($this->prifks . "§cPlease run this command in-game.");
                    }
                    return true;
                    break;

                case "cancel":
                    if ($sender instanceof Player) {
                        $playerName = $sender->getName();
                        if (isset($this->participatingPlayers[$playerName])) {
                            if ($this->isPlaying($sender)) {
                                $this->sendContainedMessage($sender, "retired");
                                $this->sendMessageInGame("§7{$playerName}§7さんがゲームをリタイアしました。");  //@TODO: sendMessageInGameの多言語対応
                                $pos = $sender->getLevel()->getSpawnLocation();
                                $sender->teleport($pos);
                                if ($sender->getGamemode() != 1) {
                                    $sender->getInventory()->clearAll();
                                    $sender->getArmorInventory()->setHelmet(Item::get(0, 0, 0));
                                    $sender->getArmorInventory()->setChestplate(Item::get(0, 0, 0));
                                    $sender->getArmorInventory()->setLeggings(Item::get(0, 0, 0));
                                    $sender->getArmorInventory()->setBoots(Item::get(0, 0, 0));
                                    $sender->getArmorInventory()->sendContents($sender);
                                }
                            } else {
                                $sender->sendMessage($this->prifks . "ゲームへの参加をキャンセルしました。");
                            }
                            $this->delPlayerFromGame($sender);
                        } else {
                            $sender->sendMessage($this->prifks . "§cあなたはどこのチームにも所属していません！");
                        }
                    } else {
                        if (isset($args[1])) {
                            if ($this->isOnlinePlayer($args[1])) {
                                $player = $this->getServer()->getPlayer($args[1]);
                                $playerName = $args[1];
                                if ($this->isPlaying($player)) {
                                    if (isset($this->participatingPlayers[$playerName])) {
                                        $this->delPlayerFromGame($player);
                                        $sender->sendMessage($this->prifks . "{$playerName}のゲームへの参加をキャンセルしました。");
                                        $player->sendMessage($this->prifks . "§c管理者によってゲームへの参加がキャンセルされました。");
                                    } else {
                                        $sender->sendMessage($this->prifks . "§c{$playerName}はゲームに参加していません。");
                                    }
                                }
                            } else {
                                $sender->sendMessage($this->prifks . "§c{$args[1]}というプレーヤーは見つかりませんでした。");
                            }
                        } else {
                            $sender->sendMessage($this->prifks . "Usage: /spvp cancel [player name]");
                        }
                    }
                    return true;
                    break;
                default:
                    return false;
            }
        } else {
            return false;
        }
    }

    public function sendStatus($args, $sender)
    {
        if ($sender instanceof Player or $sender instanceof ConsoleCommandSender)
            if (isset($args[1])) {
                switch ($args[1]) {
                    case "":
                }
            } else {
                $participatingPlayersCount = count($this->participatingPlayers);
                $onlinePlayersCount = count($this->getServer()->getOnlinePlayers());
                $gamePlayersLimit = $this->getConfig()->get("MaxNumOfPeople");
                $serverPlayerLimit = $this->getServer()->getMaxPlayers();

                if ($this->isPlaying()) {
                    $gameStatusText = "§aDuring the game";
                } else {
                    $gameStatusText = "§6Waiting for join";
                }

                $messages = array(
                    "",
                    "§b=== §fSimple SoloPVP System Status §b===",
                    "  Participating players count : {$participatingPlayersCount}/{$gamePlayersLimit}",
                    "  Online players count : {$onlinePlayersCount}/{$serverPlayerLimit}",
                    "  Game status : {$gameStatusText}",
                );

                foreach ($messages as $message) {
                    $sender->sendMessage($message);
                }
            }
    }

    public function editSettings($args, $sender)
    {
        if (isset($args[1])) {
            switch (strtolower($args[1])) {
                case "pos1":
                    if ($sender instanceof Player) {
                        $result = $this->setPosition("pos1", $sender);
                        $sender->sendMessage($this->prifks . "範囲頂点1を X:" . $result->getX() . ", Y:" . $result->getY() . ", Z:" . $result->getZ() . " に設定しました。");
                    } else {
                        $sender->sendMessage($this->prifks . "§cPlease run this command in-game.");
                    }
                    break;

                case "pos2":
                    if ($sender instanceof Player) {
                        $result = $this->setPosition("pos2", $sender);
                        $sender->sendMessage($this->prifks . "範囲頂点2を X:" . $result->getX() . ", Y:" . $result->getY() . ", Z:" . $result->getZ() . " に設定しました。");
                    } else {
                        $sender->sendMessage($this->prifks . "§cPlease run this command in-game.");
                    }
                    break;

                default:
                    $sender->sendMessage("Usage: /spvp edit < pos1 | pos2 >");
            }
        } else {
            $sender->sendMessage("Usage: /spvp edit < pos1 | pos2 >");
        }
    }

    public function setPosition($object, $player)
    {
        switch ($object) {
            case "pos1":
                $startPosition1 = new Position($player->getX(), $player->getY(), $player->getZ(), $player->getLevel());
                $this->getConfig()->set("pos1", array(
                    'world' => $startPosition1->getLevel()->getFolderName(),
                    'x' => $startPosition1->getFloorX(),
                    'y' => $startPosition1->getFloorY(),
                    'z' => $startPosition1->getFloorZ()
                ));
                $this->getConfig()->save();
                return $startPosition1;
                break;

            case "pos2":
                $startPosition2 = new Position($player->getX(), $player->getY(), $player->getZ(), $player->getLevel());
                $this->getConfig()->set("pos2", array(
                    'world' => $startPosition2->getLevel()->getFolderName(),
                    'x' => $startPosition2->getFloorX(),
                    'y' => $startPosition2->getFloorY(),
                    'z' => $startPosition2->getFloorZ()
                ));
                $this->getConfig()->save();
                return $startPosition2;
                break;

        }
    }

    public function add($player)
    {
        if ($player instanceof Player && isset($this->participatingPlayers[$player->getName()])) {
            $this->participatingPlayers[$player->getName()] = $player;
            $this->organizeArrays();
            return true;
        } else {
            return false;
        }
    }

    public function spreadPlayer(Player $player)
    {
        $data = $this->getConfig()->get("pos1");
        $level[0] = $this->getServer()->getLevelByName($data["world"]);
        $pos1 = $data;
        $data = $this->getConfig()->get("pos2");
        $level[1] = $this->getServer()->getLevelByName($data["world"]);
        $pos2 = $data;
        if ($level[0]->getFolderName() != $level[1]->getFolderName()) {
            $this->getLogger()->error("範囲頂点のワールドが異なるため、プレーヤーの分散ができませんでした。");
            return;
        }

        $randPos = new Position(
            rand($pos1["x"], $pos2["x"]),
            rand($pos1["y"], $pos2["y"]),
            rand($pos1["z"], $pos2["z"])
        );

        if ($this->eid !== null and self::DO_SEND_BOSSBAR == true) {
            API::removeBossBar([$player], $this->eid);
        }

        $player->teleport($randPos);
        if ($this->eid !== null and self::DO_SEND_BOSSBAR == true) {
            API::sendBossBarToPlayer($player, $this->eid, '残り時間');
        }
    }

///  Game  /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function gameStart()
    {
        $this->sendContainedMessage(new ConsoleCommandSender, "game-started-2");
        $this->gameRemainingSeconds = $this->getConfig()->get("Interval");

        $this->cancelAllTasks();
        $handler = $this->getServer()->getScheduler()->scheduleDelayedTask($this->tasks["GameWillEndInFiveSeconds"], 20 * ($this->getConfig()->get("Interval") - 5));
        $this->taskIDs[] = $handler->getTaskId();
        foreach ($this->participatingPlayers as $p) {
            $this->joinGame($p);
        }

        if (self::DO_SEND_BOSSBAR == true) {
            if ($this->eid === null) {
                $this->eid = API::addBossBar($this->participatingPlayers, '残り時間');
            } else {
                foreach ($this->participatingPlayers as $p) {
                    API::sendBossBarToPlayer($p, $this->eid, '残り時間');
                }
            }
        }

        $this->isPlaying = true;

        $this->brokenBlocks = array();
        $this->setBlocks = array();

        $this->organizeArrays();
        $this->sendMessageInGame("game-started-2");
    }

    public function refillItems(Player $player)
    {
        if ($player instanceof Player) {
            $item = array(
                "stone_sword" => Item::get(272, 0, 1),
                "arrow_64" => Item::get(262, 0, 64),
                "bow" => Item::get(261, 0, 1),
                "steak" => Item::get(364, 0, 64),
                "golden_apple" => Item::get(322, 0, 1),
                "wood" => Item::get(17, 0, 64),
                "glass" => Item::get(20, 0, 64),
                "potion" => Item::get(373, 22, 10)
            );
            $player->getInventory()->clearAll();
            $player->getInventory()->setItem(0, $item["stone_sword"]);
            $player->getInventory()->setItem(1, $item["bow"]);
            $player->getInventory()->setItem(2, $item["arrow_64"]);
            $player->getInventory()->setItem(3, $item["arrow_64"]);
            $player->getInventory()->setItem(4, $item["wood"]);
            $player->getInventory()->setItem(5, $item["glass"]);
            $player->getInventory()->setItem(6, $item["golden_apple"]);
            $player->getInventory()->setItem(7, $item["potion"]);
            $player->getInventory()->setItem(8, $item["steak"]);

            $player->getArmorInventory()->setHelmet(Item::get(298, 0, 1));
            $player->getArmorInventory()->setChestplate(Item::get(299, 0, 1));
            $player->getArmorInventory()->setLeggings(Item::get(300, 0, 1));
            $player->getArmorInventory()->setBoots(Item::get(301, 0, 1));
            $player->getArmorInventory()->sendContents($player);
            return true;
        } else {
            return false;
        }
    }

    public function joinGame($p)
    {
        if ($p instanceof Player) {
            $playerName = $p->getName();
            $this->spreadPlayer($p);
            $this->sendMessageInGame($playerName . "さんがゲームに参加しました。");
            if ($p->getGamemode() != 1) {
                $p->setGamemode(0);
                $this->refillItems($p);
            }

            $this->playersData[$playerName] = array(
                "kill" => 0,
                "death" => 0,
                "scores" => 0,
                "latestAttacker" => null,
                "latestCause" => null,
                "isPlaying" => true
            );
            $p->addTitle("ゲームスタート！", "", $fadein = 0, $duration = 2, $fadeout = 20);
        }
    }

    public function end($type = NULL)
    {
        $this->cancelAllTasks();

        $this->isPlaying = false;

        if (self::DO_SEND_BOSSBAR == true) {
            API::removeBossBar($this->getServer()->getOnlinePlayers(), $this->eid);
        }

        foreach ($this->playersData as $name => $data) {
            $kills[$name] = $data["kill"];
        }
        asort($kills);

        $ranking = 1;
        foreach ($kills as $name => $kill) {
            $this->playersData[$name]["ranking"] = $ranking;
            $ranking++;
        }

        foreach ($this->participatingPlayers as $player) {
            if ($player instanceof Player) {
                $name = $player->getName();
                $ranking = $this->playersData[$name]["ranking"];
                switch ($ranking) {
                    case 1:
                        $ranking = "1st";
                        break;
                    case 2:
                        $ranking = "2nd";
                        break;
                    case 3:
                        $ranking = "3rd";
                        break;
                    default:
                        $ranking = $ranking . "th";
                        break;
                }
                $kill = $this->playersData[$name]["kill"];
                $death = $this->playersData[$name]["death"];
                $gameResult = $this->getMessage("your-rank-is", $player->getLocale(), [$ranking]);
                $subtitle = $this->getMessage("your-score", $player->getLocale(), [$kill, $death]);
                if (!isset($type)) {
                    $player->addTitle($gameResult . "!", $subtitle, 10, 3, 60);
                } else {
                    switch ($type) {
                        case "too little":
                            $this->getMessage();
                            $player->addTitle("§c対戦相手がいません！", $subtitle . "§f // " . $gameResult, 10, 3, 60);
                            break;
                        case "big deviation":
                            $player->addTitle("§c人数差が発生しました！", $subtitle . "§f // " . $gameResult, 10, 3, 60);
                            break;
                    }
                }
                $pos = $player->getLevel()->getSpawnLocation();
                $player->teleport($pos);
                $player->setSpawn($pos);
                if ($player->getGamemode() != 1) {
                    $player->getInventory()->clearAll();
                    $player->getArmorInventory()->setHelmet(Item::get(0, 0, 0));
                    $player->getArmorInventory()->setChestplate(Item::get(0, 0, 0));
                    $player->getArmorInventory()->setLeggings(Item::get(0, 0, 0));
                    $player->getArmorInventory()->setBoots(Item::get(0, 0, 0));
                    $player->getArmorInventory()->sendContents($player);
                }
                $player->setNameTag($player->getName());
                $player->setDisplayName($player->getName());

                $this->playersData[$player->getName()]["isPlaying"] = false;
            }

            if (!isset($type)) {
                $this->getServer()->broadcastMessage($this->prifks . "§aゲーム終了。");
            } else {
                switch ($type) {
                    case "too little":
                        $this->getServer()->broadcastMessage($this->prifks . "§6ゲームの最小参加人数を下回ったためゲームを終了しました。");
                        break;

                    case "big deviation":
                        $this->getServer()->broadcastMessage($this->prifks . "§6チームの人数に大きな偏りが生じたためゲームを終了しました。");
                        break;
                }
            }

            $this->initPlayersData();

            $this->gameRemainingSeconds = $this->getConfig()->get("Interval");

            $handler = $this->getServer()->getScheduler()->scheduleDelayedTask($this->tasks["GameStartWait"], 20 * 15);
            $this->taskIDs[] = $handler->getTaskId();

            $this->r_count = 0;
            $this->getLogger()->info("World restoration started.");
            $this->tasks["RevivalWorld"]->onRun($this->getServer()->getTick());

            $this->organizeArrays();
        }
    }

    public function attacked(EntityDamageEvent $event)
    {
        $cause = $event->getCause();
        $damagedPlayer = $event->getEntity();
        switch ($cause) {
            case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                if (method_exists($event, "getDamager")) {
                    $damager = $event->getDamager();
                    $damaged = $event->getEntity();
                    if ($this->isPlaying($damager) and $this->isPlaying($damaged)) {
                        $damagerName = $damager->getName();
                        $damagedName = $damaged->getName();

                        $damagerPlayer = $this->getServer()->getPlayer($damagerName);
                        $damagedPlayer = $this->getServer()->getPlayer($damagedName);

                        $this->playersData[$damagedName]["latestAttacker"] = $damagerName;
                        $damagerPlayer->sendTip("§a§lAttack! >> {$damagedName}");
                        $damagedPlayer->sendTip("§c§lDamaged! << {$damagerName}");
                        $this->playersData[$damagedPlayer->getName()]["latestCause"] = EntityDamageEvent::CAUSE_ENTITY_ATTACK;
                    } else {
                        $event->setCancelled();//ゲーム外のPVPを無効化 @TODO:コンフィグで設定変更
                    }
                }
                break;

            case EntityDamageEvent::CAUSE_PROJECTILE:
                if (method_exists($event, "getDamager")) {
                    $damager = $event->getDamager();
                    $damaged = $event->getEntity();
                    if ($this->isPlaying($damager) and $this->isPlaying($damaged)) {
                        $damagerName = $damager->getName();
                        $damagedName = $damaged->getName();

                        $damagerPlayer = $this->getServer()->getPlayer($damagerName);
                        $damagedPlayer = $this->getServer()->getPlayer($damagedName);

                        $this->playersData[$damagedName]["latestAttacker"] = $damagerName;
                        $damagerPlayer->sendTip("§a§lAttack! >> {$damagedName}");
                        $damagerPlayer->getLevel()->addSound(new PopSound($damagerPlayer->getPosition()));
                        $damagedPlayer->sendTip("§c§lDamaged! << {$damagerName}");
                        $this->playersData[$damagedPlayer->getName()]["latestCause"] = EntityDamageEvent::CAUSE_PROJECTILE;
                    } else {
                        $event->setCancelled();//ゲーム外のPVPを無効化 @TODO:コンフィグで設定変更
                    }
                }
                break;

            case EntityDamageEvent::CAUSE_VOID:
                $this->playersData[$damagedPlayer->getName()]["latestCause"] = EntityDamageEvent::CAUSE_VOID;
                break;
        }
    }


    public function Death(PlayerDeathEvent $event)
    {
        if ($event->getEntity() instanceof Player && $this->isPlaying($event->getPlayer())) {
            $deadName = $event->getEntity()->getName();
            $deadData = $this->playersData[$deadName];
            $killerName = $deadData["latestAttacker"];
            switch ($deadData["latestCause"]) {
                case EntityDamageEvent::CAUSE_VOID:
                    $cause = "cause-void";
                    break;
                case EntityDamageEvent::CAUSE_FALL:
                    $cause = "cause-fall";
                    break;
                case EntityDamageEvent::CAUSE_PROJECTILE:
                    $cause = "cause-arrow";
            }//@TODO: 死亡原因を表示。

            $event->setDrops([new Item(Item::COOKED_BEEF, 0, rand(1, 3))]);
            $this->sendMessageInGame("0-was-killed-by-1", [$deadName, $killerName]);
            $this->playersData[$deadName]["death"]++;
            $this->playersData[$killerName]["kill"]++;

            $event->setDeathMessage("");
        }
    }

    public function onBreakBlock(BlockBreakEvent $event)
    {
        if ($this->isPlaying($event->getPlayer())) {
            $block = $event->getBlock();
            $pos = new Vector3($block->getFloorX(), $block->getFloorY(), $block->getFloorZ());
            $this->brokenBlocks[] = [$block, $pos];
        }
    }

    public function onSetBlock(BlockPlaceEvent $event)
    {
        if ($this->isPlaying($event->getPlayer())) {
            $block = $event->getBlock();
            $pos = new Vector3($block->getFloorX(), $block->getFloorY(), $block->getFloorZ());
//            $defaultBlock = $this->getServer()->getLevelByName($this->getConfig()->get("pos1")["world"])->getBlock($pos);
            $defaultBlock = Block::get(0);
            $this->setBlocks[] = [$defaultBlock, $pos];
        }
    }

    public function Respawn(PlayerRespawnEvent $event)
    {
        $player = $event->getPlayer();
        if ($this->isPlaying($player)) {
            $this->refillItems($player);
        }
        $this->spreadPlayer($player);
    }

//    public function ProjectileLaunch(ProjectileLaunchEvent $event)
//    {
//        $entity = $event->getEntity();
//        $launcherDisplayName = $entity->getNameTag();
//        if (!$this->isOnlinePlayer($launcherDisplayName)) {
//            $launcherTTLength = mb_strlen(preg_replace("/(.*)§f]§r(.*)/", '$1', $launcherDisplayName)) + 5;
//            $launcherName = mb_substr($launcherDisplayName, $launcherTTLength);
//        } else {
//            $launcherName = $launcherDisplayName;
//        }
//        echo "display:$launcherDisplayName";
//        $launcher = $this->getServer()->getPlayer($launcherName);
//        $item = Item::get(Item::SNOWBALL, 0, 3);
//        if (!$launcher->getInventory()->contains($item)) {
//            if ($launcher->getGamemode() != 1) {
//                $launcher->setGamemode(2);
//                $item = array(
//                    "snowball_16" => Item::get(332, 0, 16),
//                    "tunic" => Item::get(229, 0, 1)
//
//                );
//                $launcher->getInventory()->clearAll();
//                $launcher->getInventory()->setItem(0, $item["snowball_16"]);
//            }
//        } else {
//
//        }
//
//    }//玉の自動補充 破損してる

    public function sendMessageInGame($message, $array = [])
    {
        foreach ($this->participatingPlayers as $player) {
            if ($player instanceof Player) {
                $lang = $player->getLocale();
                $player->sendMessage($this->prifks . $this->getMessage($message, $lang, $array));
            }
        }
    }

    public function isOnlinePlayer($name)
    {
        $return = false;
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            if ($player->getName() == $name) {
                $return = true;
                break;
            }
        }
        return $return;
    }

    public function delPlayerFromGame($player)
    {
        $arrayNum = array_search($player, $this->participatingPlayers);
        $this->participatingPlayers = array_splice($this->participatingPlayers, $arrayNum, 1);
        $this->organizeArrays();
    }

/// System /////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function isPlaying($player = null)
    {
        if ($player = null) {
            return $this->isPlaying;
        } else {
            if ($player instanceof Player) {
                $playerName = $player->getName();
            } else {
                $playerName = $player;
            }
            return $this->playersData[$playerName]["isPlaying"];
        }
    }

    public function organizeArrays()
    {
//        foreach ($this->participatingPlayers as $player) {
//            //do something
//        }
        $this->participatingPlayers = array_merge($this->participatingPlayers);

        if ($this->isPlaying()) {
            if (count($this->participatingPlayers) <= 1) {
                $this->end("too little");
            }
        }
    }

    public function initPlayersData()
    {
        $this->playersData = array();
    }

    //Score/////////////////////////////////////////////////////////////

    //Chat//////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function onChat(PlayerChatEvent $e)
    {

    }

    //Scheduler/////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function cancelAllTasks()
    {
        foreach ($this->taskIDs as $id) {
            $this->cancelTask($id);
        }
//        $this->getLogger()->notice("Scheduler >> All tasks of [Snow Ball Fight] were canceled.");
    }

    public function cancelTask($taskID)
    {
        $this->getServer()->getScheduler()->cancelTask($taskID);
    }


    public function sendBossBar()
    {
        if (self::DO_SEND_BOSSBAR == true) {
            if ($this->eid === null) return;
            $maxTimeLimit = $this->getConfig()->get("Interval");
            $current = $this->gameRemainingSeconds;
            $percentage = $current / $maxTimeLimit * 100;
            API::setPercentage($percentage, $this->eid, $this->participatingPlayers);

            $minutes = floor(($current / 60) % 60);
            $seconds = $current % 60;
            $hms = sprintf("%02d:%02d", $minutes, $seconds);
            API::setTitle("§l残り時間\n\n§l§6[" . $hms . "]", $this->eid, $this->participatingPlayers);
        } else {
            return;
        }
    }

    // Language ////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function getMessage(string $code = "", string $lang = "en_UK", array $replaces = [])
    {
        if ($lang == "en_UK") $lang = "en_US";
        if (!isset($this->messages[$lang])) $lang = "en_US";
        if (isset($this->messages[$lang][$code])) {
//            $replaces = func_get_args();
//            array_shift($replaces);
//            array_shift($replaces);
            $needle = array();
            foreach ($replaces as $key => $replace) {
                $needle[] = "{%" . $key . "}";
            }
            return str_replace($needle, $replaces, $this->messages[$lang][$code]);
        } else {
            return $code;
        }
    }

    public function sendContainedMessage($sender, $code, $array = [])
    {
        switch (true) {
            case $sender instanceof ConsoleCommandSender:
                $lang = $this->getServer()->getLanguage()->getLang();
                break;
            case $sender instanceof Player:
                $lang = $sender->getLocale();
                break;
            default:
                $lang = "en_UK";
        }
        $sender->sendMessage($this->prifks . $this->getMessage($code, $lang, $array));
    }

//    public function move(PlayerMoveEvent $event)
//    {
//        $player = $event->getPlayer();
//        if (!$this->playersData[$player->getName()]["onGround"]) {
//            if (($player->getPosition()->getY() % 1) == 0) {
//                $this->playersData[$player->getName()]["onGround"] = true;
//                echo $player->getName() . "is on ground.\n";
//            }
//        }
//    }

    // From API ////////////////////////////////////////////////////////////////////////////////////////////////////////
    /* Quotes from CreateColorArmor_v1.0.1 by vardo@お鳥さん
     * website : https://forum.pmmp.jp/threads/697/
     * Thanks to the author.
     */
    public function giveColorArmor(Player $player, Item $item, String $colonm)
    {
        $tempTag = new CompoundTag("", []);
        $color = $this->getColorByName($colonm);
        $tempTag->customColor = new IntTag("customColor", $color);
        $item->setCompoundTag($tempTag);

        switch ($item->getId()) {
            case "298":
                $player->getArmorInventory()->setHelmet($item);
                break;

            case "299":
                $player->getArmorInventory()->setChestplate($item);
                break;

            case "300":
                $player->getArmorInventory()->setLeggings($item);
                break;


            case "301":
                $player->getArmorInventory()->setBoots($item);
                break;
        }

        $player->getArmorInventory()->sendContents($player);

        switch ($item->getId()) {
            case "302":
            case "303":
            case "304":
            case "305":
            case "306":
            case "307":
            case "308":
            case "309":
            case "310":
            case "311":
            case "312":
            case "313":
            case "314":
            case "315":
            case "316":
            case "317":
                $this->getLogger()->notice("CCA>> 革装備にのみ適用可能です");
                break;
        }
    }

    public function getColorByName(String $name)
    {
        $colorNum = strtoupper($name);
        switch ($colorNum) {
            case "RED":
            case "赤":
                return "16711680";
                break;

            case "ORANGE":
            case "オレンジ":
                return "16744192";
                break;

            case "BLUE":
            case "青":
                return "255";
                break;

            case "AQUA":
            case "アクア":
                return "39372";
                break;

            case "GREEN":
            case "緑":
                return "3100463";
                break;

            case "LIME":
            case "黄緑":
                return "3329330";
                break;

            case "PINK":
            case "ピンク":
                return "15379946";
                break;

            case "PURPLE":
            case "紫":
                return "8388736";
                break;

            case "WHITE":
            case "白":
                return "16777215";
                break;

            case "GRAY":
            case "灰":
                return "12632256";
                break;

            case "LIGHTGRAY":
            case "薄灰":
                return "14211263";
                break;

            case "BLACK":
            case "黒":
                return "0";
                break;

            case "MAGENTA":
            case "マゼンタ":
                return "16711935";
                break;

            case "BROWN":
            case "茶":
                return "10824234";
                break;

            case "CYAN":
            case "シアン":
                return "35723";
                break;

            case "SKY":
            case "空":
                return "65535";
                break;

            case "YELLOW":
            case "黄":
                return "16776960";
                break;

            case "GOLD":
            case "金":
                return "14329120";
                break;

            case "SILVER":
            case "銀":
                return "15132922";
                break;

            case "BRONZE":
            case "銅":
                return "9205843";
                break;
            default:
                return false;
                break;
        }
    }
}
