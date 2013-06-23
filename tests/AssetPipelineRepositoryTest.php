<?php

use Mockery as m;
use Codesleeve\AssetPipeline\AssetPipelineRepository;

/**
 * @author  <kelt@dockins.org>
 * 
 * I was unable to use vfsStream for mocking the filesystem because
 * apparently Assetic isn't able to get the files from the 
 * vfs:// stream so I ended up making a fake real filesystem
 * under tests/root so I can test. Maybe it's using realpath() or
 * something? Oh well.. at least the code is tested somewhat right?
 */
class AssetPipelineRepositoryTest extends PHPUnit_Framework_TestCase
{

    /**
     * Allows us to test protected functions inside of the assetpipelinerepository
     * I don't really want to publicly expose these since they aren't really part
     * of the Asset facade but I do want to test them
     * 
     * @param  [type] $name [description]
     * @param  array  $args [description]
     * @return [type]       [description]
     */
    protected function callMethod($name, $args = array())
    {
        $class = new ReflectionClass($this->pipeline);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($this->pipeline, $args);
    }

    /**
     * Setup a project path, config and pipeline to use
     */
    public function setUp()
    {
        $this->projectPath = __DIR__ . '/root/project';
        $this->pipeline = $this->new_pipeline();
    }

    /**
     * [tearDown description]
     * @return [type] [description]
     */
    public function tearDown()
    {
        m::close();
    }

    /**
     * [new_pipeline description]
     * @return [type] [description]
     */
    public function new_pipeline($config_data_overrides = array())
    {
        $config_data = array_merge(array(
            'asset-pipeline::path' => 'app/assets',
            'asset-pipeline::minify' => true,
            'asset-pipeline::compressed' => array('.min.', '-min.'),
            'asset-pipeline::ignores' => array('/test/', '/tests/'),
        ), $config_data_overrides);

        $config = $this->getMock('Config', array('get'));       
        $config->expects($this->any())
             ->method('get')
             ->with($this->anything())
             ->will($this->returnCallback(function($path) use ($config_data) {
                if (array_key_exists($path, $config_data)) {
                    return $config_data[$path];
                }

                return $path;
             }));

        return new AssetPipelineRepository($this->projectPath, $config);
    }

    /**
     * [testCanCreateJavascript description]
     * @return [type] [description]
     */
    public function testCanCreateJavascript()
    {        
        $outcome = $this->pipeline->javascripts();

        $this->assertContains("alert('underscore.js')", $outcome);
        $this->assertContains("alert('backbone.js')", $outcome);
        $this->assertContains("alert('jquery.js')", $outcome);
        $this->assertContains("alert('file1.js')", $outcome);
        $this->assertContains("alert('file2.min.js');", $outcome);
        $this->assertContains("alert('file3-min.js');", $outcome);
        $this->assertContains("alert('app1.js')", $outcome);
    }

    /**
     * The coffeescript in our /coffeescripts/awesome.coffee should
     * get parsed into some javascript like what is below
     * 
     * @return [type] [description]
     */
    public function testCanCreateCoffeeScript()
    {
        $outcome = $this->pipeline->javascripts();
        $this->assertContains("function(x){return x*x}", $outcome);
    }

    /**
     * These test files shouldn't even be in the source code at all
     * so that allows us to keep our tests outside of the source but
     * still within the application assets folder
     * 
     * @return [type] [description]
     */
    public function testCanIgnorePatterns()
    {
        $outcome = $this->pipeline->javascripts();
        $this->assertNotContains("alert('test.js')", $outcome);
        $this->assertNotContains("alert('tests.js')", $outcome);
    }

    /**
     * We test for a semi-colon below because those would be removed
     * if we were running minification on those two files
     * 
     * @return [type] [description]
     */
    public function testCanIgnoreCompressedPatterns()
    {
        $outcome = $this->pipeline->javascripts();
        $this->assertContains("alert('file2.min.js');", $outcome);
        $this->assertContains("alert('file3-min.js');", $outcome);
    }

    /**
     * [testCanIncludeDifferentJavascriptDirectory description]
     * @return [type] [description]
     */
    public function testCanIncludeDifferentJavascriptDirectory()
    {
        $outcome = $this->pipeline->javascripts('application/scripts');
        $this->assertContains("alert('file1.js')", $outcome);
    }

    /**
     * [testCanCreateStylesheets description]
     * @return [type] [description]
     */
    public function testCanCreateStylesheets()
    {        
        $outcome = $this->pipeline->stylesheets();
        $this->assertContains('.styles1{color:red}', $outcome);
        $this->assertContains('.styles2{color:white}', $outcome);
        $this->assertContains('.styles3{color:blue}', $outcome);
    }

    /**
     * [testCanCreateStylesheetsWithLess description]
     * @return [type] [description]
     */
    public function testCanCreateStylesheetsWithLess()
    {
        $outcome = $this->pipeline->stylesheets();
        $this->assertContains('.box{color:#123456}', $outcome);
    }

    /**
     * [testCanHandleInvalidBaseDirectory description]
     * @expectedException InvalidArgumentException
     * @return [type] [description]
     */
    public function testCanHandleInvalidBaseDirectory()
    {
        $this->projectPath = __DIR__ . "/root/invalid_path";
        $pipeline = $this->new_pipeline();
    }

    /**
     * [testCanHandleTemplates description]
     * @return [type] [description]
     */
    public function testCanHandleHtml()
    {
        $outcome = $this->pipeline->htmls();
        $this->assertContains('<div class="test">', $outcome);
        $this->assertContains('<script type="text/x-handlebars-template">', $outcome);
    }

    /**
     * [testCanHandleSpecificJsFile description]
     * @return [type] [description]
     */
    public function testCanHandleSpecificJsFile()
    {
        $outcome = $this->pipeline->javascripts('application/scripts/file1.js');
        $this->assertEquals("alert('file1.js');", $outcome);
    }

    /**
     * [testCanHandleSpecificCoffeeFile description]
     * @return [type] [description]
     */
    public function testCanHandleSpecificCoffeeFile()
    {
        $outcome = $this->pipeline->javascripts('javascripts/coffeescripts/awesome.coffee');
        $this->assertContains("function(x){return x*x}", $outcome);
    }

    /**
     * [testCanHandleSpecificCssFile description]
     * @return [type] [description]
     */
    public function testCanHandleSpecificCssFile()
    {
        $outcome = $this->pipeline->stylesheets('stylesheets/styles1.css');
        $this->assertContains('.styles1{color:red}', $outcome);
        $this->assertNotContains('.styles2{color:white}', $outcome);
    }

    /**
     * [testCanHandleSpecificLessFile description]
     * @return [type] [description]
     */
    public function testCanHandleSpecificLessFile()
    {
        $outcome = $this->pipeline->stylesheets('stylesheets/admin/testing.less');
        $this->assertContains('.box{color:#123456}', $outcome);
    }

    /**
     * [testCanHandleSpecificLessFile description]
     * @return [type] [description]
     */
    public function testCanHandleSpecificHtmlFile()
    {
        $outcome = $this->pipeline->htmls('templates/test.html');
        $this->assertContains('<div class="test">', $outcome);
        $this->assertNotContains('<script type="text/x-handlebars-template">', $outcome);
    }

    /**
     * [testPrecendenceTopDownForJs description]
     * @return [type] [description]
     */
    public function testPrecendenceForJs()
    {
        $outcome = $this->pipeline->javascripts();
        $this->assertContains("alert('backbone.js')", $outcome);
        $this->assertContains("alert('app1.js')", $outcome);

        $jquery = strpos($outcome, "alert('jquery.js')");
        $app1 = strpos($outcome, "alert('app1.js')");

        $this->assertLessThan($app1, $jquery);
    }

    /**
     * [testPrecendenceTopDownForJs description]
     * @return [type] [description]
     */
    public function testPrecendenceForCss()
    {
        $outcome = $this->pipeline->stylesheets();
        $this->assertContains(".box", $outcome);
        $this->assertContains(".styles1", $outcome);

        $box = strpos($outcome, ".box");
        $styles1 = strpos($outcome, ".styles1");

        $this->assertLessThan($styles1, $box);
    }

    /**
     * [testPrecendenceTopDownForJs description]
     * @return [type] [description]
     */
    public function testPrecendenceForHtml()
    {
        $outcome = $this->pipeline->htmls();

        $this->assertContains("atemplate", $outcome);
        $this->assertContains("{{something}}", $outcome);
        $this->assertContains("{{hmm}}", $outcome);

        $atemplate = strpos($outcome, "atemplate");
        $something = strpos($outcome, "{{something}}");
        $hmm = strpos($outcome, "{{hmm}}");

        $this->assertLessThan($something, $atemplate);
        $this->assertLessThan($hmm, $something);
    }


    // public function testHmm()
    // {
    //     $files = $this->callMethod('gather_assets', array(
    //         __DIR__ . '/root/project/app/assets/precedence',
    //         array('js', 'coffee')
    //     ));

    //     //$files = array_reverse($files);

    //     foreach($files as $file) {
    //         print str_replace('C:\Users\kelt\Dropbox\htdocs\codesleeve4\workbench\codesleeve\asset-pipeline\tests/root/project/app/assets/precedence', '', $file) . PHP_EOL;
    //     }
    // }


}
