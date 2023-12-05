<?php

declare(strict_types=1);

namespace SkulZOnTheYT\SKV;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\world\ChunkPopulateEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\world\World;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\normal\Normal;
use pocketmine\world\generator\populator\Populator;
use SkulZOnTheYT\SKV\populator\PopulatorVillage;
use SkulZOnTheYT\SKV\scheduler\ChunkPopulationTask;
use SkulZOnTheYT\SKV\structure\VillagePieces;

class SKVillages extends PluginBase implements Listener {
    
    public const DEBUG = false;

    private static $instance;

    private $populators = [];

    public function onLoad(): void {
        self::$instance = $this;
    }

    public function onEnable(): void {
        try {
        // Initialize village pieces
        VillagePieces::init();

        // Register events
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @param LevelLoadEvent $event
     * @priority MONITOR
     */
    public function onWorldLoad(WorldLoadEvent $event): void {
        $populators = [];
        $world = $event->getWorld();
        $generator = $world->getGenerator();
        if ($generator->getId() !== Generator::TYPE_FLAT && $generator->getDimension() === Level::DIMENSION_OVERWORLD) {
            $populators[] = new PopulatorVillage($generator instanceof Normal);
        }
        $this->populators[spl_object_id($level)] = $populators;
    }

    /**
     * @param ChunkPopulateEvent $event
     * @priority MONITOR
     */
    public function onChunkPopulate(ChunkPopulateEvent $event): void {
        $level = $event->getLevel();
        $populators = $this->populators[spl_object_id($level)] ?? [];
        if (!empty($populators)) {
            $this->getScheduler()->scheduleAsyncTask(new ChunkPopulationTask($level, $event->getChunk(), $populators));
        }
    }

    /**
     * @param WorldUnloadEvent $event
     * @priority MONITOR
     */
    public function onWorldUnload(WorldUnloadEvent $event): void {
        unset($this->populators[spl_object_id($event->getWorld())]);
    }

   public static function getInstance() : self {
	    return self::$instance;
	 }
}
