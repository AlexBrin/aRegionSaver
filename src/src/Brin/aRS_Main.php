<?php

	namespace Brin;

	use pocketmine\plugin\PluginBase;
	use pocketmine\utils\Config;
	use pocketmine\math\Vector3;

	use pocketmine\block\Block;
	use pocketmine\block\Air;
	use pocketmine\block\WallSign;
	use pocketmine\block\SignPost;
	use pocketmine\block\Door;
	use pocketmine\block\Chest;
	use pocketmine\block\FlowerPot;

	use pocketmine\tile\Tile;
	use pocketmine\tile\Sign as SignTile;
	use pocketmine\tile\Chest as ChestTile;
	use pocketmine\tile\FlowerPot as PotTile;

	use pocketmine\command\Command;
	use pocketmine\command\CommandSender;

	use pocketmine\level\Level;

	use pocketmine\nbt\tag\CompoundTag;
	use pocketmine\nbt\tag\IntTag;
	use pocketmine\nbt\tag\StringTag;

	use pocketmine\Player;

	use Brin\BuildTask;

	class aRS_Main extends PluginBase {
		private $position = [
							'x' => [],
							'y' => [],
							'z' => []
						];

		const PREFIX = '§7[§baRegionSaver§7]§r';

		public function onEnable() {
			$f = $this->getDataFolder();
			if(!is_dir($f)) {
				@mkdir($f);
				@mkdir($f.'regions');
			}
		}

		public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
			if(!$sender instanceof Player)
				return;

			if(count($args) != 2) {
				$sender->sendMessage($this->help());
				return;
			}

			$folder = $this->getDataFolder();

			switch(mb_strtolower($args[0])) {
				case 'save':
						if(!$sender->hasPermission('ars.save')) {
							$sender->sendmessage(self::PREFIX.' §cНет прав');
							return;
						}

						switch(mb_strtolower($args[1])) {

							case 'pos':
							case 'pos1':
							case 'pos2': // Почему нет?
									if(count($this->position['x']) == 2) {
										$sender->sendMessage(self::PREFIX." §cУже выбрано 2 точки. Сбросить: /ars save reset");
										return;
									}
									$this->position['x'][] = $sender->getFloorX();
									$this->position['y'][] = $sender->getFloorY();
									$this->position['z'][] = $sender->getFloorZ();
									$sender->sendMessage(self::PREFIX." §aПозиция выбрана");
								break;

							case 'reset':
									$this->position = [
										'x' => [],
										'y' => [],
										'z' => []
									];
									$sender->sendMessage(self::PREFIX." §aПозиции очищены");
								break;

							default: 
									if(count($this->position['x']) != 2) {
										$sender->sendMessage(self::PREFIX." §cНедостаточно точек координат...");
										return;
									}
									$rgname = mb_strtolower($args[1]);
									if(file_exists($folder.'regions/'.$rgname)) {
										$sender->sendMessage(self::PREFIX." §eТакой регион уже сохранен. Выбери другое название");
										return;
									}
									$sender->sendMessage(self::PREFIX." §eСобираем блоки...");
									$sender->sendMessage(self::PREFIX." §e§lВозможны зависания");
									$position = [
										'x' => [
											min($this->position['x'][0], $this->position['x'][1]),
											max($this->position['x'][0], $this->position['x'][1])
										],
										'y' => [
											min($this->position['y'][0], $this->position['y'][1]),
											max($this->position['y'][0], $this->position['y'][1])
										],
										'z' => [
											min($this->position['z'][0], $this->position['z'][1]),
											max($this->position['z'][0], $this->position['z'][1])
										],
									];
									$this->position = ['x' => [], 'y' => [], 'z' => []];
									$level = $sender->getLevel();
									$region = [ 
										'x'      => $position['x'][1] - $position['x'][0], 
										'y'      => $position['y'][1] - $position['y'][0], 
										'z'      => $position['z'][1] - $position['z'][0], 
										'blocks' => []
									];
									for($x = $position['x'][0]; $x <= $position['x'][1]; $x++)
										for($y = $position['y'][0]; $y <= $position['y'][1]; $y++)
											for($z = $position['z'][0]; $z <= $position['z'][1]; $z++)
												$region['blocks'][] = $level->getBlock(new Vector3($x, $y, $z));
									$sender->sendMessage(self::PREFIX." §aСбор блоков завершен");
									$sender->sendMessage(self::PREFIX." §e§lСохраняем регион...");

									$i = 0;
									$block = [];
									foreach($region['blocks'] as $bl) {

										$block[$i] = [
											'id'   => $bl->getId(),
											'meta' => $bl->getDamage()
										];

										if($bl instanceof WallSign or $bl instanceof SignPost) {
											$block[$i]['type'] = 'sign';
											$tile = $level->getTile(new Vector3($bl->x, $bl->y, $bl->z));
											if($tile instanceof SignTile)
												$block[$i]['text'] = $tile->getText();
											else
												$block[$i]['text'] = ['', '', '', ''];
										}
										elseif($bl instanceof Door) {
											$block[$i]['type'] = 'door';
											$block[$i]['meta'] ^= 0x04;
										}
										elseif($bl instanceof Chest) {
											$block[$i]['type'] = 'chest';
											$block[$i]['customname'] = $bl->getName();
										}
										elseif($bl instanceof FlowerPot) {
											$block[$i]['type'] = 'pot';
											$item = $level->getTile($bl)->getItem();
											$block[$i]['item'] = [
												'id' => $item->getId(),
												'meta' => $item->getDamage()
											];
										}
										else 
											$block[$i]['type'] = 'block';

										$i++;
									}
									$region['blocks'] = $block;

									$rg = new Config($folder.'regions/'.$rgname.'.json', Config::JSON);
									$rg->setAll($region);
									$rg->save();

									$sender->sendMessage(self::PREFIX.' §aСохранение региона '.$rgname.' завершено');

						}

					break;

				case 'paste':
						if(!$sender->hasPermission('ars.paste')) {
							$sender->sendmessage(self::PREFIX.' §cНет прав');
							return;
						}

						$level = $sender->level;
						if(!isset($args[1])) {
							$sender->sendMessage(self::PREFIX." Use: /ars paste <имя региона>");
							return;
						}
						$rgname = mb_strtolower($args[1]);
						$f = "{$folder}regions/$rgname.json";

						if(!file_exists($f)) {
							$sender->sendMessage(self::PREFIX.' Такого региона не сохранено');
							return;
						}

						$sender->sendMessage(self::PREFIX.' §eВставка региона...');

						$region = new Config($f, Config::JSON);
						$region = $region->getAll();

						$this->getServer()->getScheduler()->scheduleRepeatingTask(new BuildTask($this, $region, $level, $sender), 1);

					break;

				default:
						$sender->sendMessage($this->help());
			}
		}

		public function help() {
			$help = [
				self::PREFIX." Help:",
				"/ars <save> <имя региона> - сохранить регион",
				"/ars <save> <pos1/pos2> - выбрать позицию",
				"/ars <paste> <имя региона> - вставить регион",
			];
			return implode("\n", $help);
		}

	}

?>