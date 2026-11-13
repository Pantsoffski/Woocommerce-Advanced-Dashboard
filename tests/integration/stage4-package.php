<?php
/** Verify actual installed candidate against the local artifact manifest. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ADWC_INTEGRATION_SANDBOX' ) || ! ADWC_INTEGRATION_SANDBOX || ! in_array( get_option( 'siteurl' ), array( 'http://127.0.0.1:38973', 'http://127.0.0.1:38974', 'http://127.0.0.1:38975' ), true ) ) { throw new RuntimeException( 'Stage 4 sandboxes only.' ); }
$manifest = json_decode( file_get_contents( '/adwc-manifest.json' ), true, 512, JSON_THROW_ON_ERROR );
if ( hash_file( 'sha256', '/adwc-local-rc.zip' ) !== $manifest['sha256'] ) { throw new RuntimeException( 'Archive hash mismatch.' ); }
$root = WP_PLUGIN_DIR . '/advanced-dashboard-for-woocommerce/';
$actual = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( $file->isFile() ) { $actual[] = substr( $file->getPathname(), strlen( $root ) ); }
}
$expected = array_keys( $manifest['files'] ); sort( $actual ); sort( $expected );
if ( $actual !== $expected ) { throw new RuntimeException( 'Unexpected installed files.' ); }
foreach ( $manifest['files'] as $path => $info ) {
	if ( hash_file( 'sha256', $root . $path ) !== $info['sha256'] ) { throw new RuntimeException( 'Installed hash mismatch: ' . $path ); }
}
WP_CLI::success( 'All ' . count( $actual ) . ' installed files match the inspected ZIP manifest; no extra/developer files.' );
