<?php

namespace App\DataFixtures\Modules\Goals;

use App\DataFixtures\Providers\Modules\Goals;
use App\Entity\Modules\Goals\MyGoals;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Faker\Factory;

class MyGoalsFixtures extends Fixture implements OrderedFixtureInterface
{
    /**
     * Factory $faker
     */
    private $faker;

    /**
     * @var Goals
     */
    private $provider_goals;

    public function __construct() {
        $this->faker          = Factory::create('en');
        $this->provider_goals = new Goals();
    }

    public function load(ObjectManager $manager)
    {

        foreach($this->provider_goals::ALL_GOALS as $name => $subgoals) {

            $display_on_dashboard = $this->faker->boolean;
            $name                 = $this->faker->word;
            $description          = $this->faker->text(150);

            $my_goal = new MyGoals();
            $my_goal->setName($name);
            $my_goal->setDescription($description);
            $my_goal->setDisplayOnDashboard($display_on_dashboard);

            $manager->persist($my_goal);
        }

        $manager->flush();
    }

    /**
     * Get the order of this fixture
     *
     * @return integer
     */
    public function getOrder() {
        return 10;
    }
}
