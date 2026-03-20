<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Asset_Mapper\Asset_Mapper;
use Symfony\Component\Asset_Mapper\Asset_Mapper_Compiler;
use Symfony\Component\Asset_Mapper\Asset_Mapper_Dev_Server_Subscriber;
use Symfony\Component\Asset_Mapper\Asset_Mapper_Interface;
use Symfony\Component\Asset_Mapper\Asset_Mapper_Repository;
use Symfony\Component\Asset_Mapper\Command\Asset_Mapper_Compile_Command;
use Symfony\Component\Asset_Mapper\Command\Compress_Assets_Command;
use Symfony\Component\Asset_Mapper\Command\Debug_Asset_Mapper_Command;
use Symfony\Component\Asset_Mapper\Command\Import_Map_Audit_Command;
use Symfony\Component\Asset_Mapper\Command\Import_Map_Install_Command;
use Symfony\Component\Asset_Mapper\Command\Import_Map_Outdated_Command;
use Symfony\Component\Asset_Mapper\Command\Import_Map_Remove_Command;
use Symfony\Component\Asset_Mapper\Command\Import_Map_Require_Command;
use Symfony\Component\Asset_Mapper\Command\Import_Map_Update_Command;
use Symfony\Component\Asset_Mapper\Compiled_Asset_Mapper_Config_Reader;
use Symfony\Component\Asset_Mapper\Compiler\Css_Asset_Url_Compiler;
use Symfony\Component\Asset_Mapper\Compiler\Java_Script_Import_Path_Compiler;
use Symfony\Component\Asset_Mapper\Compiler\Source_Mapping_Urls_Compiler;
use Symfony\Component\Asset_Mapper\Compressor\Brotli_Compressor;
use Symfony\Component\Asset_Mapper\Compressor\Chain_Compressor;
use Symfony\Component\Asset_Mapper\Compressor\Compressor_Interface;
use Symfony\Component\Asset_Mapper\Compressor\Gzip_Compressor;
use Symfony\Component\Asset_Mapper\Compressor\Zstandard_Compressor;
use Symfony\Component\Asset_Mapper\Factory\Cached_Mapped_Asset_Factory;
use Symfony\Component\Asset_Mapper\Factory\Mapped_Asset_Factory;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Auditor;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Config_Reader;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Generator;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Manager;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Renderer;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Update_Checker;
use Symfony\Component\Asset_Mapper\Import_Map\Import_Map_Version_Checker;
use Symfony\Component\Asset_Mapper\Import_Map\Remote_Package_Downloader;
use Symfony\Component\Asset_Mapper\Import_Map\Remote_Package_Storage;
use Symfony\Component\Asset_Mapper\Import_Map\Resolver\Js_Delivr_Esm_Resolver;
use Symfony\Component\Asset_Mapper\Mapper_Aware_Asset_Package;
use Symfony\Component\Asset_Mapper\Path\Local_Public_Assets_Filesystem;
use Symfony\Component\Asset_Mapper\Path\Public_Assets_Path_Resolver;
return static function (Container_Configurator $container): void {
    $container->services()->set('asset_mapper', Asset_Mapper::class)->args([service('asset_mapper.repository'), service('asset_mapper.mapped_asset_factory'), service('asset_mapper.compiled_asset_mapper_config_reader')])->alias(Asset_Mapper_Interface::class, 'asset_mapper')->alias('asset_mapper.http_client', 'http_client')->set('asset_mapper.mapped_asset_factory', Mapped_Asset_Factory::class)->args([service('asset_mapper.public_assets_path_resolver'), service('asset_mapper_compiler'), abstract_arg('vendor directory')])->set('asset_mapper.cached_mapped_asset_factory', Cached_Mapped_Asset_Factory::class)->args([service('.inner'), param('kernel.cache_dir') . '/asset_mapper', param('kernel.debug')])->decorate('asset_mapper.mapped_asset_factory')->set('asset_mapper.repository', Asset_Mapper_Repository::class)->args([abstract_arg('array of asset mapper paths'), param('kernel.project_dir'), abstract_arg('array of excluded path patterns'), abstract_arg('exclude dot files'), param('kernel.debug')])->set('asset_mapper.public_assets_path_resolver', Public_Assets_Path_Resolver::class)->args([abstract_arg('asset public prefix')])->set('asset_mapper.local_public_assets_filesystem', Local_Public_Assets_Filesystem::class)->args([abstract_arg('public directory')])->set('asset_mapper.compiled_asset_mapper_config_reader', Compiled_Asset_Mapper_Config_Reader::class)->args([abstract_arg('public assets directory')])->set('asset_mapper.asset_package', Mapper_Aware_Asset_Package::class)->decorate('assets._default_package')->args([service('.inner'), service('asset_mapper')])->set('asset_mapper.dev_server_subscriber', Asset_Mapper_Dev_Server_Subscriber::class)->args([service('asset_mapper'), abstract_arg('asset public prefix'), abstract_arg('extensions map'), service('cache.asset_mapper'), service('profiler')->null_on_invalid()])->tag('kernel.event_subscriber')->set('asset_mapper.command.compile', Asset_Mapper_Compile_Command::class)->args([service('asset_mapper.compiled_asset_mapper_config_reader'), service('asset_mapper'), service('asset_mapper.importmap.generator'), service('asset_mapper.local_public_assets_filesystem'), param('kernel.project_dir'), param('kernel.debug'), service('event_dispatcher')->null_on_invalid()])->tag('console.command')->set('asset_mapper.command.debug', Debug_Asset_Mapper_Command::class)->args([service('asset_mapper'), service('asset_mapper.repository'), param('kernel.project_dir')])->tag('console.command')->set('asset_mapper_compiler', Asset_Mapper_Compiler::class)->args([tagged_iterator('asset_mapper.compiler'), service_closure('asset_mapper')])->set('asset_mapper.compiler.css_asset_url_compiler', Css_Asset_Url_Compiler::class)->args([abstract_arg('missing import mode'), service('logger')])->tag('asset_mapper.compiler')->tag('monolog.logger', ['channel' => 'asset_mapper'])->set('asset_mapper.compiler.source_mapping_urls_compiler', Source_Mapping_Urls_Compiler::class)->tag('asset_mapper.compiler')->set('asset_mapper.compiler.javascript_import_path_compiler', Java_Script_Import_Path_Compiler::class)->args([service('asset_mapper.importmap.config_reader'), abstract_arg('missing import mode'), service('logger')])->tag('asset_mapper.compiler')->tag('monolog.logger', ['channel' => 'asset_mapper'])->set('asset_mapper.importmap.config_reader', Import_Map_Config_Reader::class)->args([abstract_arg('importmap.php path'), service('asset_mapper.importmap.remote_package_storage')])->set('asset_mapper.importmap.manager', Import_Map_Manager::class)->args([service('asset_mapper'), service('asset_mapper.importmap.config_reader'), service('asset_mapper.importmap.remote_package_downloader'), service('asset_mapper.importmap.resolver')])->alias(Import_Map_Manager::class, 'asset_mapper.importmap.manager')->set('asset_mapper.importmap.generator', Import_Map_Generator::class)->args([service('asset_mapper'), service('asset_mapper.compiled_asset_mapper_config_reader'), service('asset_mapper.importmap.config_reader')])->set('asset_mapper.importmap.remote_package_storage', Remote_Package_Storage::class)->args([abstract_arg('vendor directory')])->set('asset_mapper.importmap.remote_package_downloader', Remote_Package_Downloader::class)->args([service('asset_mapper.importmap.remote_package_storage'), service('asset_mapper.importmap.config_reader'), service('asset_mapper.importmap.resolver')])->set('asset_mapper.importmap.version_checker', Import_Map_Version_Checker::class)->args([service('asset_mapper.importmap.config_reader'), service('asset_mapper.importmap.remote_package_downloader')])->set('asset_mapper.importmap.resolver', Js_Delivr_Esm_Resolver::class)->args([service('asset_mapper.http_client')])->set('asset_mapper.importmap.renderer', Import_Map_Renderer::class)->args([service('asset_mapper.importmap.generator'), service('assets.packages')->null_on_invalid(), param('kernel.charset'), abstract_arg('polyfill URL'), abstract_arg('script HTML attributes'), service('request_stack')])->set('asset_mapper.importmap.auditor', Import_Map_Auditor::class)->args([service('asset_mapper.importmap.config_reader'), service('asset_mapper.http_client')])->set('asset_mapper.importmap.update_checker', Import_Map_Update_Checker::class)->args([service('asset_mapper.importmap.config_reader'), service('asset_mapper.http_client')])->set('asset_mapper.importmap.command.require', Import_Map_Require_Command::class)->args([service('asset_mapper.importmap.manager'), service('asset_mapper.importmap.version_checker'), param('kernel.project_dir')])->tag('console.command')->set('asset_mapper.importmap.command.remove', Import_Map_Remove_Command::class)->args([service('asset_mapper.importmap.manager')])->tag('console.command')->set('asset_mapper.importmap.command.update', Import_Map_Update_Command::class)->args([service('asset_mapper.importmap.manager'), service('asset_mapper.importmap.version_checker')])->tag('console.command')->set('asset_mapper.importmap.command.install', Import_Map_Install_Command::class)->args([service('asset_mapper.importmap.remote_package_downloader'), param('kernel.project_dir')])->tag('console.command')->set('asset_mapper.importmap.command.audit', Import_Map_Audit_Command::class)->args([service('asset_mapper.importmap.auditor')])->tag('console.command')->set('asset_mapper.importmap.command.outdated', Import_Map_Outdated_Command::class)->args([service('asset_mapper.importmap.update_checker')])->tag('console.command')->set('asset_mapper.compressor.brotli', Brotli_Compressor::class)->set('asset_mapper.compressor.zstandard', Zstandard_Compressor::class)->set('asset_mapper.compressor.gzip', Gzip_Compressor::class)->set('asset_mapper.compressor', Chain_Compressor::class)->args([abstract_arg('compressor'), service('logger')])->alias(Compressor_Interface::class, 'asset_mapper.compressor')->set('asset_mapper.assets.command.compress', Compress_Assets_Command::class)->args([service('asset_mapper.compressor')])->tag('console.command');
};