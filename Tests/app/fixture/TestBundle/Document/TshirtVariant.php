<?php

namespace ONGR\ApiBundle\Tests\app\fixture\TestBundle\Document;

use ONGR\ElasticsearchBundle\Annotation as ES;

/**
 * @ES\Nested
 */
class TshirtVariant
{
    /**
     * @var
     *
     * @ES\Property(type="string")
     */
    public $color;

    /**
     * @var
     *
     * @ES\Property(type="string")
     */
    public $size;
}