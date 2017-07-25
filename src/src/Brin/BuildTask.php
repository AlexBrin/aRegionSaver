<?php

	namespace Brin;

	use pocketmine\scheduler\PluginTask;
	use pocketmine\math\Vector3;
	use pocketmine\level\Level;

	use pocketmine\block\Block;
	use pocketmine\block\Air;
	use pocketmine\block\WallSign;
	use pocketmine\block\SignPost;
	use pocketmine\block\Door;

	use pocketmine\tile\Tile;
	use pocketmine\tile\Chest as ChestTile;

	use pocketmine\nbt\NBT;
	use pocketmine\nbt\tag\CompoundTag;
	use pocketmine\nbt\tag\IntTag;
	use pocketmine\nbt\tag\StringTag;
	use pocketmine\nbt\tag\ListTag;
	use pocketmine\nbt\tag\ShortTag;

	use Brin\aRS_Main as main;

	class BuildTask extends PluginTask {

		public function __construct($plugin, &$blocks, $level, $player) {
			parent::__construct($plugin);
			$this->player   = $player;
			$this->level    = $level;
			$this->startX   = $player->getFloorX();
			$this->startY   = $player->getFloorY();
			$this->startZ   = $player->getFloorZ();
			$this->currentX = $this->startX;
			$this->currentY = $this->startY;
			$this->currentZ = $this->startZ;
			$this->endX     = $this->startX + $blocks['x'];
			$this->endY     = $this->startY + $blocks['y'];
			$this->endZ     = $this->startZ + $blocks['z'];
			$this->blocks   = $blocks['blocks'];
			$this->i        = 0;
		}

		public function onRun($tick) {
			$meta = $this->blocks[$this->i]['meta'];
			$v3 = new Vector3($this->currentX, $this->currentY, $this->currentZ);
			
			if($this->blocks[$this->i]['type'] == 'door')
				$meta ^= 0x04;

			$block = Block::get(
							$this->blocks[$this->i]['id'],
							$meta
						);
			
			$this->level->setBlock(
					$v3,
					$block,
					true
				);

			if($this->blocks[$this->i]['type'] == 'sign') {
				$nbt = new CompoundTag("", [
						"id" => new StringTag("id", Tile::SIGN),
						"x"  => new IntTag("x", $v3->x),
						"y"  => new IntTag("y", $v3->y),
						"z"  => new IntTag("z", $v3->z),
						"Text1" => new StringTag("Text1", $this->blocks[$this->i]['text'][0]),
						"Text2" => new StringTag("Text2", $this->blocks[$this->i]['text'][1]),
						"Text3" => new StringTag("Text3", $this->blocks[$this->i]['text'][2]),
						"Text4" => new StringTag("Text4", $this->blocks[$this->i]['text'][3]),
					]);
				Tile::createTile(Tile::SIGN, $this->level, $nbt);
			}
			elseif($this->blocks[$this->i]['type'] == 'chest') {
				$nbt = new CompoundTag("", [
						new ListTag("Items", []),
						new StringTag("id", Tile::CHEST),
						new StringTag("CustomName", $this->blocks[$this->i]['customname']),
						new IntTag("x", $v3->x),
						new IntTag("y", $v3->y),
						new IntTag("z", $v3->z)
					]);
				$nbt->Items->setTagType(NBT::TAG_Compound);
				$currentTile = Tile::createTile(Tile::CHEST, $this->level, $nbt);

				$faces = [
					0 => 4,
					1 => 2,
					2 => 5,
					3 => 3,
				];
				$chest = null;
				for($side = 2; $side <= 5; ++$side) {
					if(($meta === 4 or $meta === 5) && ($side === 4 or $side === 5))
						continue;
					elseif(($meta === 3 or $meta === 2) and ($side === 2 or $side === 3))
						continue;
					$c = $block->getSide($side);
					if($c->getId() == $block->getId() and $c->getDamage() == $block->getDamage()) {
						$tile = $this->level->getTile($c);
						if($tile instanceof Chest and !$tile->isPaired()) {
							$chest = $tile;
							break;
						}
					}
				}

				if($chest instanceof ChestTile && $currentTile instanceof ChestTile) {
					$chest->pairWith($currentTile);
					$currentTile->pairWith($chest);
				}

			}
			elseif($this->blocks[$this->i]['type'] == 'pot') {
				$nbt = new CompoundTag("", [
						new StringTag("id", Tile::FLOWER_POT),
						new IntTag("x", $v3->x),
						new IntTag("y", $v3->y),
						new IntTag("z", $v3->z),
						new ShortTag("item", $this->blocks[$this->i]['item']['id']),
						new IntTag("mData", $this->blocks[$this->i]['item']['meta'])
					]);
				Tile::createTile(Tile::FLOWER_POT, $this->level, $nbt);
			}

			if($this->currentZ == $this->endZ) {
				$this->currentZ = $this->startZ;

				if($this->currentY == $this->endY) {
					$this->currentY = $this->startY;
					$this->currentX++;
				}
				else
					$this->currentY++;
			}
			else
				$this->currentZ++;

			if(($this->i + 1) == count($this->blocks)) {
				$this->player->sendMessage(main::PREFIX.' §aРегион вставлен');
				$this->getOwner()->getServer()->getScheduler()->cancelTask($this->getTaskId());
			}
			else
				$this->i++;
		}

	}

?>