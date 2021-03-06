<?php
/*
 * This file is part of the Tacit package.
 *
 * Copyright (c) Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tacit\Test\Controller;


use Tacit\Controller\Restful;
use Tacit\Transform\ArrayTransformer;

class MockRestful extends Restful
{
    protected static $allowedMethods = ['OPTIONS', 'HEAD', 'GET', 'POST'];

    public function get()
    {
        $this->respondWithItem(['message' => 'mock me do you?'], new ArrayTransformer());
    }

    public function post()
    {
        $target = $this->app->request()->post('target', 'undefined');
        $this->respondWithItem(['message' => "mock me do you {$target}?"], new ArrayTransformer());
    }
}
