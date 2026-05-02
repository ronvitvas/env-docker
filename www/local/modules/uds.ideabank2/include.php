<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(
    'uds.ideabank2',
    [
        '\\Uds\\Ideabank2\\AjaxApi'                               => 'lib/Uds/Ideabank2/AjaxApi.php',
        '\\Uds\\Ideabank2\\Domain\\IdeaService'                   => 'lib/Uds/Ideabank2/Domain/IdeaService.php',
        '\\Uds\\Ideabank2\\Domain\\CoinService'                   => 'lib/Uds/Ideabank2/Domain/CoinService.php',
        '\\Uds\\Ideabank2\\Domain\\ChallengeService'              => 'lib/Uds/Ideabank2/Domain/ChallengeService.php',
        '\\Uds\\Ideabank2\\Domain\\ContestService'                => 'lib/Uds/Ideabank2/Domain/ContestService.php',
        '\\Uds\\Ideabank2\\Domain\\ExpertReviewService'           => 'lib/Uds/Ideabank2/Domain/ExpertReviewService.php',
        '\\Uds\\Ideabank2\\Domain\\CommitteeService'              => 'lib/Uds/Ideabank2/Domain/CommitteeService.php',
        '\\Uds\\Ideabank2\\Domain\\NotificationService'           => 'lib/Uds/Ideabank2/Domain/NotificationService.php',
        '\\Uds\\Ideabank2\\Domain\\TaskIntegrationService'        => 'lib/Uds/Ideabank2/Domain/TaskIntegrationService.php',
        '\\Uds\\Ideabank2\\Domain\\PublicDataService'             => 'lib/Uds/Ideabank2/Domain/PublicDataService.php',
        '\\Uds\\Ideabank2\\Config\\Feature'                       => 'lib/Uds/Ideabank2/Config/Feature.php',
        '\\Uds\\Ideabank2\\Config\\ModuleOptions'                 => 'lib/Uds/Ideabank2/Config/ModuleOptions.php',
        '\\Uds\\Ideabank2\\Config\\SelfCheck'                     => 'lib/Uds/Ideabank2/Config/SelfCheck.php',
        '\\Uds\\Ideabank2\\Debug\\DebugAuth'                       => 'lib/Uds/Ideabank2/Debug/DebugAuth.php',
        '\\Uds\\Ideabank2\\Seed\\DemoDataSeeder'                  => 'lib/Uds/Ideabank2/Seed/DemoDataSeeder.php',
        '\\Uds\\Ideabank2\\Admin\\Menu'                           => 'lib/Uds/Ideabank2/Admin/Menu.php',
        '\\Uds\\Ideabank2\\Events\\IdeaEvents'                    => 'lib/Uds/Ideabank2/Events/IdeaEvents.php',
        '\\Uds\\Ideabank2\\Events\\CoinEvents'                    => 'lib/Uds/Ideabank2/Events/CoinEvents.php',
        '\\Uds\\Ideabank2\\Table\\IdeaTable'                      => 'lib/Uds/Ideabank2/Table/IdeaTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaAuthorTable'                => 'lib/Uds/Ideabank2/Table/IdeaAuthorTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaCategoryTable'              => 'lib/Uds/Ideabank2/Table/IdeaCategoryTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaChallengePartTable'         => 'lib/Uds/Ideabank2/Table/IdeaChallengePartTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaChallengeTable'             => 'lib/Uds/Ideabank2/Table/IdeaChallengeTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaCoinTable'                  => 'lib/Uds/Ideabank2/Table/IdeaCoinTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaCommentTable'               => 'lib/Uds/Ideabank2/Table/IdeaCommentTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaCommitteeTable'             => 'lib/Uds/Ideabank2/Table/IdeaCommitteeTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaContestPartTable'           => 'lib/Uds/Ideabank2/Table/IdeaContestPartTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaContestTable'               => 'lib/Uds/Ideabank2/Table/IdeaContestTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaExpertReviewTable'          => 'lib/Uds/Ideabank2/Table/IdeaExpertReviewTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaFeedbackTable'              => 'lib/Uds/Ideabank2/Table/IdeaFeedbackTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaFileTable'                  => 'lib/Uds/Ideabank2/Table/IdeaFileTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaNewsTable'                  => 'lib/Uds/Ideabank2/Table/IdeaNewsTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaReactionTable'              => 'lib/Uds/Ideabank2/Table/IdeaReactionTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaRewardRuleTable'            => 'lib/Uds/Ideabank2/Table/IdeaRewardRuleTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaStatusTable'                => 'lib/Uds/Ideabank2/Table/IdeaStatusTable.php',
        '\\Uds\\Ideabank2\\Table\\IdeaWorkflowTable'              => 'lib/Uds/Ideabank2/Table/IdeaWorkflowTable.php',
    ]
);
