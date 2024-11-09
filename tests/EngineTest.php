<?php

use PHPUnit\Framework\TestCase;
use Kennyth01\PhpRulesEngine\Rule;
use Kennyth01\PhpRulesEngine\Engine;

class EngineTest extends TestCase
{

    /**
     * Define a rule that checks if the player is fouled out
     * Foul out any player who:
     * (has committed 5 fouls AND game is 40 minutes) OR (has committed 6 fouls AND game is 48 minutes)
     *
     *
     * See tests/data/rule.player.isFouledOut.json
     *
     * Note: This example demonstates nested boolean logic - e.g. (personalFoulCount is >= 5 AND gameDuration is equal 40) OR (personalFoulCount is not lesser than 6 AND gameDuration is equal 48).
     * @return void
     */
    public function testPlayerIsFouledOut()
    {
        $engine = new Engine();
        $rule = json_decode(file_get_contents('tests/data/rule.player.isFouledOut.json'), true);

        $engine->addRule(new Rule($rule));
        $engine->addFact('personalFoulCount', 6);
        $engine->addFact('gameDuration', 40);

        $engine->setTargetRule('rule.player.isFouledOut');

        $result = $engine->evaluate();
        $expectedResult = [
            [
                'type' => 'fouledOut',
                'params' => [
                    'message' => 'Player has fouled out!'
                ],
                'facts' => [
                    'personalFoulCount' => 6,
                    'gameDuration' => 40

                ],
                'interpretation' => '((gameDuration is equal to 40 AND personalFoulCount is >= 5) OR (gameDuration is equal to 48 AND NOT (personalFoulCount is less than 6)))'
            ]
        ];

        $this->assertEquals($expectedResult, $result);
    }


    /**
     * Define a rule that checks if the profile is completed
     * Profile is considered completed if the following attributes are not null:
     * - username
     * - birthdayYear
     * - profilePic
     * - primaryLocation
     *
     * See tests/data/rule.profile.isCompleted.json
     * @return void
     */
    public function testProfileIsCompleted()
    {
        $engine = new Engine();
        $rule = json_decode(file_get_contents('tests/data/rule.profile.isCompleted.json'), true);

        $engine->addRule(new Rule($rule));
        $engine->addFact('profile', [
            'attributes' => [
                'username' => null,
                'birthdayYear' => 1990,
                'profilePic' => 'https://example.com/profile.jpg',
                'primaryLocation' => 'New York',
            ]
        ]);

        $engine->setTargetRule('rule.profile.isCompleted');

        $result = $engine->evaluate();

        // Expected result
        $expectedResult = [
            [
                'type' => 'rule.profile.isCompleted',
                'params' => [
                    'value' => false,
                    'message' => 'Profile is not completed',
                ],
                'facts' => [
                    'profile' => [
                        'attributes' => [
                            'username' => null,
                            'birthdayYear' => 1990,
                            'profilePic' => 'https://example.com/profile.jpg',
                            'primaryLocation' => 'New York',
                        ]
                    ]
                ],
                'interpretation' => '(NOT (username is equal to NULL) AND NOT (birthdayYear is equal to NULL) AND NOT (profilePic is equal to NULL) AND NOT (primaryLocation is equal to NULL))',
                'failedConditions' => [
                    [
                        'not' => [
                            'fact' => 'profile',
                            'path' => '$.attributes.username',
                            'value' => NULL,
                            'operator' => 'equal'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Define a rule that checks if the profile is searchable
     * Profile is searchable if the profile is active and not hidden
     * Profile is considered active if the following attributes are false:
     * - isDeleted
     * - isDeactivated
     * - isSuspended
     * Profile is considered hidden if isHidden is true
     *
     *
     * Note: This demonstrates the use of the profile.isActive rule as a condition for the profile.isSearchable rule
     * @return void
     */
    public function testProfileIsSearchable()
    {
        // Step 1: Instantiate the engine
        $engine = new Engine();

        // Step 2: Add the profile.isActive rule
        $engine->addRule(new Rule([
            'name' => 'profile.isActive',
            'conditions' => [
                'all' => [
                    ['not' => ['fact' => 'profile', 'path' => '$.isDeleted', 'value' => true, 'operator' => 'equal']],
                    ['not' => ['fact' => 'profile', 'path' => '$.isDeactivated', 'value' => true, 'operator' => 'equal']],
                    ['not' => ['fact' => 'profile', 'path' => '$.isSuspended', 'value' => true, 'operator' => 'equal']],
                ]
            ],
            'event' => [
                'type' => 'profile.isActive',
                'params' => ['isActive' => true, 'message' => 'Profile is in active state']
            ],
            'failureEvent' => [
                'type' => 'profile.isActive',
                'params' => ['isActive' => false, 'message' => 'Profile is not in active state']
            ]
        ]));

        // Step 3: Add the profile.isSearchable rule
        $engine->addRule(new Rule([
            'name' => 'profile.isSearchable',
            'conditions' => [
                'any' => [
                    ['condition' => 'profile.isActive'],
                    ['not' => ['fact' => 'profile', 'path' => '$.isHidden', 'value' => true, 'operator' => 'equal']],
                ]
            ],
            'event' => [
                'type' => 'profile.isSearchable',
                'params' => ['isSearchable' => true, 'message' => 'Profile is searchable']
            ],
            'failureEvent' => [
                'type' => 'profile.isSearchable',
                'params' => ['isSearchable' => false, 'message' => 'Profile is not searchable']
            ]
        ]));

        // Step 4: Add facts
        $engine->addFact('profile', [
            'isDeleted' => false,
            'isDeactivated' => false,
            'isSuspended' => false,
            'isHidden' => false
        ]);

        // Step 5: Set the target rule
        $engine->setTargetRule('profile.isSearchable');

        // Step 6: Evaluate the engine
        $result = $engine->evaluate();

        // Expected result
        $expectedResult = [
            [
                'type' => 'profile.isSearchable',
                'params' => [
                    'isSearchable' => true,
                    'message' => 'Profile is searchable',
                ],
                'facts' => [
                    'profile' => [
                        'isDeleted' => false,
                        'isDeactivated' => false,
                        'isSuspended' => false,
                        'isHidden' => false
                    ]
                ],
                'interpretation' => '(profile.isActive OR NOT (isHidden is equal to 1))'
            ]
        ];

        // Step 7: Assert the result matches the expected result
        $this->assertEquals($expectedResult, $result);
    }
}
