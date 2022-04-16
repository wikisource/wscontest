<?php
return [
	'file_suppressions' => [
		'src/Command/ScoreCommand.php' => [ 'PhanTypeMismatchArgument', 'PhanTypeMismatchDimFetch' ],
		'src/Controller/ContestsController.php' => [
			'PhanAccessMethodInternal', 'PhanTypeArraySuspiciousNullable', 'PhanUnreferencedUseNormal',
		],
		'src/Controller/HomeController.php' => [ 'PhanUnreferencedUseNormal' ],
	],
];
