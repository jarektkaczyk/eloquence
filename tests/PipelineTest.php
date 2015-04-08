<?php namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Sofa\Eloquence\Pipeline\Pipeline;

class PipelineTest extends \PHPUnit_Framework_TestCase {

    /** 
     * @test
     * @covers \Sofa\Eloquence\Pipeline\Pipeline::to
     * @covers \Sofa\Eloquence\Pipeline\Pipeline::send
     */
    public function it_delivers_the_parcel_to_destination()
    {
        $pipeline    = new Pipeline;
        $parcel      = 'start';
        $destination = function ($parcel) {
            return 'end';
        };

        $this->assertEquals('end', $pipeline->send($parcel)->to($destination));
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Pipeline\Pipeline::to
     * @covers \Sofa\Eloquence\Pipeline\Pipeline::through
     */
    public function it_calls_pipes_in_the_same_order_they_were_provided()
    {
        $pipeline    = new Pipeline;
        $parcel      = 'start';
        $destination = function ($parcel) {
            return $parcel . ',end';
        };
        $pipes = [
            function ($next, $parcel) { $parcel .= ',first'; return $next($parcel); },
            function ($next, $parcel) { $parcel .= ',second'; return $next($parcel); },
            function ($next, $parcel) { $parcel .= ',third'; return $next($parcel); },
        ];

        $result = $pipeline->send($parcel)->through($pipes)->to($destination);
    
        $this->assertEquals('start,first,second,third,end', $result);
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Pipeline\Pipeline::to
     * @covers \Sofa\Eloquence\Pipeline\Pipeline::with
     * @covers \Sofa\Eloquence\Pipeline\Pipeline::__construct
     */
    public function it_passes_additional_arguments_along_with_the_parcel()
    {
        $parcel      = 'start';
        $pipes = [
            function ($next, $parcel, $args) {
                $parcel .= ',pipe-' . $args->get('foo');
                return $next($parcel, $args); 
            },
        ];
        $destination = function ($parcel, $args) {
            return $parcel . ',end-' . $args->get('foo');
        };
        $pipeline    = new Pipeline($pipes);

        $args = m::mock('Sofa\Eloquence\Contracts\ArgumentBag');
        $args->shouldReceive('get')->with('foo')->twice()->andReturn('bar');

        $result = $pipeline->send($parcel)->with($args)->to($destination);
    
        $this->assertEquals('start,pipe-bar,end-bar', $result);
    }
}
