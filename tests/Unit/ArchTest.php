<?php

arch('no debugging calls are left in the app')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
