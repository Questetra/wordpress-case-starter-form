<?php
/**
 * Plugin Name: Questetra Case Starter Form (Reference)
 * Description: 【リファレンス実装】メッセージ開始イベント (HTTP) を kick する専用フォームを、ショートコード [qscf_form] で固定ページ／投稿に埋め込みます。送信はサーバサイドで行うため URL と API Key はページソースに露出しません。対応データ型は「文字(1行)」「文字(複数行)」「ファイル」。項目は先頭の設定配列で増減でき、バリデーションエラーは各フィールドごとに表示します。
 * Version:     2.5.0
 * Author:      Questetra (reference implementation)
 * License:     MIT
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================================
 *  ■■■ 設定：通常はこのブロックだけ編集すれば動きます ■■■
 *  これより下（ロジック本体）は触らなくてOKです。
 * ========================================================================== */

// 開始イベントの起動 URL（末尾の ?key= は付けない）※自分のイベントURLに置き換えてください
define( 'QSCF_ENDPOINT_URL', 'https://template.questetra.net/System/Event/MessageStart/1823/11/start' );

// API Key（サーバサイドでのみ使用。ページソースには出ません）
//   ↓ 自分の開始イベントのキーに置き換えてください。
//   公開リポジトリ等に載せる場合は直書きせず、wp-config.php 等から読む形を推奨:
//     例) wp-config.php に  define( 'QSCF_API_KEY', 'xxxxx' );  と書き、ここでは下記を削除。
if ( ! defined( 'QSCF_API_KEY' ) ) {
	define( 'QSCF_API_KEY', 'PUT-YOUR-API-KEY-HERE' );
}

/* --- 送信するフォーム項目（受信パラメータに合わせて増減する） --------------
 *  各項目: array(
 *    'param'    => Questetra 側の受信パラメータ名（半角英数字・_ のみ。action/qscf_* と重複させない）
 *    'label'    => 画面に出すラベル
 *    'type'     => 'text'（文字1行）/ 'textarea'（文字複数行）/ 'file'（ファイル）
 *    'required' => true で必須（text/textarea は入力必須、file は「添付そのものが必須」）。省略時は任意
 *  )
 *  ※ ファイルは常に複数添付可。個数の上限/下限（○個以上 等）は Questetra 側の項目設定で
 *     検証され、エラーは該当フィールドに表示されます（本プラグインは個数を持ちません）。
 * ------------------------------------------------------------------------- */
define( 'QSCF_FIELDS', array(
	array( 'param' => 'title',     'label' => '件名',       'type' => 'text',     'required' => false ),
	array( 'param' => 'q_string0', 'label' => '文字単一行', 'type' => 'text',     'required' => true ),
	array( 'param' => 'q_string1', 'label' => '文字複数行', 'type' => 'textarea', 'required' => true ),
	array( 'param' => 'q_file11',  'label' => 'ファイル',   'type' => 'file',     'required' => true ),
) );

// 1ファイルあたりの上限サイズ（バイト）
define( 'QSCF_MAX_FILE_BYTES', 10 * 1024 * 1024 );

// 送信成功時のサンクスメッセージ
define( 'QSCF_THANKS_MESSAGE', 'ご送信ありがとうございました。ケースを開始しました。' );

/* ============================================================================
 *  ■■■ これより下はロジック本体：通常は編集不要 ■■■
 * ========================================================================== */

add_shortcode( 'qscf_form', 'qscf_render_shortcode' );

function qscf_render_shortcode( $atts ) {
	if ( isset( $_GET['qscf_status'] ) && 'success' === $_GET['qscf_status'] && ! empty( $_GET['qscf_txn'] ) ) {
		$txn  = sanitize_text_field( wp_unslash( $_GET['qscf_txn'] ) );
		$data = get_transient( 'qscf_txn_' . $txn );
		if ( false !== $data ) {
			delete_transient( 'qscf_txn_' . $txn );
			return qscf_render_success( $data );
		}
	}
	$errors = array();
	$old    = array();
	if ( isset( $_GET['qscf_status'] ) && 'error' === $_GET['qscf_status'] && ! empty( $_GET['qscf_txn'] ) ) {
		$txn = sanitize_text_field( wp_unslash( $_GET['qscf_txn'] ) );
		$e   = get_transient( 'qscf_err_' . $txn );
		if ( is_array( $e ) ) {
			delete_transient( 'qscf_err_' . $txn );
			$errors = isset( $e['errors'] ) ? $e['errors'] : array();
			$old    = isset( $e['old'] ) ? $e['old'] : array();
		}
	}
	return qscf_render_form( $errors, $old );
}

function qscf_render_form( $errors = array(), $old = array() ) {
	$action_url = esc_url( admin_url( 'admin-post.php' ) );
	$nonce      = wp_create_nonce( 'qscf_submit' );

	$return_url = get_permalink();
	if ( ! $return_url ) { $return_url = home_url( '/' ); }
	$return_url = esc_url( $return_url );

	ob_start();

	if ( ! empty( $errors['_general'] ) ) {
		echo '<div class="qscf-error">' . esc_html( $errors['_general'] ) . '</div>';
	}
	?>
	<form class="qscf-form" action="<?php echo $action_url; ?>" method="post" enctype="multipart/form-data">
		<input type="hidden" name="action" value="qscf_submit" />
		<input type="hidden" name="qscf_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
		<input type="hidden" name="qscf_return" value="<?php echo $return_url; ?>" />

		<?php foreach ( QSCF_FIELDS as $fld ) :
			$param   = $fld['param'];
			$param_a = esc_attr( $param );
			$label   = esc_html( $fld['label'] );
			$req     = ! empty( $fld['required'] );
			$err     = isset( $errors[ $param ] ) ? $errors[ $param ] : '';
			$has_err = ( '' !== $err );
			$val     = isset( $old[ $param ] ) ? $old[ $param ] : '';
			$pclass  = $has_err ? ' qscf-has-error' : '';

			if ( 'text' === $fld['type'] || 'textarea' === $fld['type'] ) :
				$is_area = ( 'textarea' === $fld['type'] );
			?>
			<p class="qscf-field qscf-field-<?php echo $is_area ? 'textarea' : 'text'; ?><?php echo $pclass; ?>">
				<label for="qscf_<?php echo $param_a; ?>"><?php echo $label; ?><?php if ( $req ) : ?> <span class="qscf-required">*</span><?php endif; ?></label><br />
				<?php if ( $is_area ) : ?>
				<textarea id="qscf_<?php echo $param_a; ?>" name="<?php echo $param_a; ?>" rows="4" maxlength="5000" <?php echo $req ? 'required' : ''; ?>><?php echo esc_textarea( $val ); ?></textarea>
				<?php else : ?>
				<input type="text" id="qscf_<?php echo $param_a; ?>" name="<?php echo $param_a; ?>" maxlength="200" value="<?php echo esc_attr( $val ); ?>" <?php echo $req ? 'required' : ''; ?> />
				<?php endif; ?>
				<?php if ( $has_err ) : ?><span class="qscf-field-error"><?php echo esc_html( $err ); ?></span><?php endif; ?>
			</p>
			<?php elseif ( 'file' === $fld['type'] ) : ?>
			<p class="qscf-field qscf-field-file<?php echo $pclass; ?>">
				<label for="qscf_<?php echo $param_a; ?>"><?php echo $label; ?><?php if ( $req ) : ?> <span class="qscf-required">*</span><?php endif; ?></label><br />
				<input type="file" id="qscf_<?php echo $param_a; ?>" name="<?php echo $param_a; ?>[]" multiple <?php echo $req ? 'required' : ''; ?> />
				<?php if ( $has_err ) : ?><span class="qscf-field-error"><?php echo esc_html( $err ); ?></span><?php endif; ?>
			</p>
			<?php endif; ?>
		<?php endforeach; ?>

		<p class="qscf-submit"><button type="submit">送信</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function qscf_render_success( $data ) {
	ob_start();
	?>
	<div class="qscf-success-wrapper">
		<p class="qscf-thanks"><?php echo esc_html( QSCF_THANKS_MESSAGE ); ?></p>
		<h3 class="qscf-submitted-heading">送信内容</h3>
		<table class="qscf-submitted-table"><tbody>
		<?php foreach ( $data as $label => $value ) : ?>
			<tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'admin_post_qscf_submit', 'qscf_handle_submit' );
add_action( 'admin_post_nopriv_qscf_submit', 'qscf_handle_submit' );

function qscf_handle_submit() {
	$return = isset( $_POST['qscf_return'] ) ? esc_url_raw( wp_unslash( $_POST['qscf_return'] ) ) : home_url();
	$txn    = wp_generate_password( 16, false );

	if ( ! isset( $_POST['qscf_nonce'] ) || ! wp_verify_nonce( $_POST['qscf_nonce'], 'qscf_submit' ) ) {
		qscf_redirect_error( $return, $txn, array( '_general' => 'セッションの有効期限が切れました。もう一度お試しください。' ), array() );
	}

	$errors      = array();
	$old         = array();
	$text_fields = array();
	$file_items  = array();
	$summary     = array();

	foreach ( QSCF_FIELDS as $fld ) {
		$param = $fld['param'];
		$label = $fld['label'];

		if ( 'text' === $fld['type'] || 'textarea' === $fld['type'] ) {
			$raw = isset( $_POST[ $param ] ) ? wp_unslash( $_POST[ $param ] ) : '';
			$val = ( 'textarea' === $fld['type'] ) ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			$old[ $param ] = $val;
			if ( ! empty( $fld['required'] ) && '' === $val ) {
				$errors[ $param ] = '「' . $label . '」を入力してください。';
			}
			$text_fields[ $param ] = $val;
			$summary[ $label ]     = '' !== $val ? $val : '(未入力)';

		} elseif ( 'file' === $fld['type'] ) {
			$collected = array();
			$too_big   = false;
			if ( ! empty( $_FILES[ $param ] ) && isset( $_FILES[ $param ]['name'] ) && is_array( $_FILES[ $param ]['name'] ) ) {
				$ff = $_FILES[ $param ];
				$n  = count( $ff['name'] );
				for ( $i = 0; $i < $n; $i++ ) {
					if ( UPLOAD_ERR_OK !== $ff['error'][ $i ] || '' === $ff['name'][ $i ] ) {
						continue;
					}
					if ( $ff['size'][ $i ] > QSCF_MAX_FILE_BYTES ) {
						$too_big = true;
						continue;
					}
					$collected[] = array(
						'param' => $param,
						'name'  => sanitize_file_name( $ff['name'][ $i ] ),
						'type'  => ! empty( $ff['type'][ $i ] ) ? $ff['type'][ $i ] : 'application/octet-stream',
						'tmp'   => $ff['tmp_name'][ $i ],
					);
				}
			}
			if ( $too_big ) {
				$errors[ $param ] = '1ファイルあたり ' . size_format( QSCF_MAX_FILE_BYTES ) . ' 以下にしてください。';
			} elseif ( ! empty( $fld['required'] ) && empty( $collected ) ) {
				$errors[ $param ] = '「' . $label . '」を添付してください。';
			}
			$file_items = array_merge( $file_items, $collected );

			$names = array();
			foreach ( $collected as $c ) { $names[] = $c['name']; }
			$summary[ $label ] = implode( ', ', $names );
		}
	}

	// プラグイン側バリデーションでエラーがあれば、まとめて差し戻し
	if ( ! empty( $errors ) ) {
		qscf_redirect_error( $return, $txn, $errors, $old );
	}

	// Questetra へ送信（個数などの詳細バリデーションは Questetra 側で行われる）
	$boundary = wp_generate_password( 24, false );
	$body     = qscf_build_multipart( $boundary, $text_fields, $file_items );
	$endpoint = add_query_arg( 'key', QSCF_API_KEY, QSCF_ENDPOINT_URL );
	$response = wp_remote_post( $endpoint, array(
		'timeout' => 30,
		'headers' => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
		'body'    => $body,
	) );

	if ( is_wp_error( $response ) ) {
		qscf_redirect_error( $return, $txn, array( '_general' => '送信エラー: ' . $response->get_error_message() ), $old );
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 400 ) {
		$errors = qscf_parse_questetra_errors( wp_remote_retrieve_body( $response ), $code );
		qscf_redirect_error( $return, $txn, $errors, $old );
	}

	$summary['ケースID'] = trim( wp_remote_retrieve_body( $response ) );
	set_transient( 'qscf_txn_' . $txn, $summary, 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( array( 'qscf_status' => 'success', 'qscf_txn' => $txn ), $return ) );
	exit;
}

/**
 * Questetra のバリデーションエラー XML を param => message に変換。
 * 受信パラメータ名に一致する <key> はそのフィールドへ、それ以外は _general へ。
 */
function qscf_parse_questetra_errors( $xml, $code ) {
	$errors  = array();
	$general = array();
	$params  = array();
	foreach ( QSCF_FIELDS as $fld ) { $params[] = $fld['param']; }

	if ( $xml && preg_match_all( '/<error>(.*?)<\/error>/s', $xml, $blocks ) ) {
		foreach ( $blocks[1] as $blk ) {
			$key = '';
			$det = '';
			if ( preg_match( '/<key>(.*?)<\/key>/s', $blk, $mk ) )      { $key = trim( $mk[1] ); }
			if ( preg_match( '/<detail>(.*?)<\/detail>/s', $blk, $md ) ) { $det = trim( $md[1] ); }
			if ( '' === $det ) { continue; }
			if ( '' !== $key && in_array( $key, $params, true ) ) {
				$errors[ $key ] = $det;
			} else {
				$general[] = ( '' !== $key ? $key . ': ' : '' ) . $det;
			}
		}
	}
	if ( empty( $errors ) && empty( $general ) ) {
		$general[] = '送信に失敗しました（HTTP ' . $code . '）。';
	}
	if ( ! empty( $general ) ) {
		$errors['_general'] = implode( ' / ', $general );
	}
	return $errors;
}

function qscf_build_multipart( $boundary, $fields, $file_items ) {
	$eol  = "\r\n";
	$body = '';
	foreach ( $fields as $name => $value ) {
		$body .= '--' . $boundary . $eol;
		$body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
		$body .= $value . $eol;
	}
	foreach ( $file_items as $fl ) {
		$data  = file_get_contents( $fl['tmp'] );
		$body .= '--' . $boundary . $eol;
		$body .= 'Content-Disposition: form-data; name="' . $fl['param'] . '"; filename="' . $fl['name'] . '"' . $eol;
		$body .= 'Content-Type: ' . $fl['type'] . $eol . $eol;
		$body .= $data . $eol;
	}
	$body .= '--' . $boundary . '--' . $eol;
	return $body;
}

function qscf_redirect_error( $return, $txn, $errors, $old ) {
	set_transient( 'qscf_err_' . $txn, array( 'errors' => $errors, 'old' => $old ), 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( array( 'qscf_status' => 'error', 'qscf_txn' => $txn ), $return ) );
	exit;
}

add_action( 'wp_enqueue_scripts', 'qscf_enqueue_styles' );
function qscf_enqueue_styles() {
	$css = '
	.qscf-form .qscf-field { margin-bottom: 1em; }
	.qscf-form label { font-weight: 600; }
	.qscf-form input[type="text"], .qscf-form textarea { width: 100%; max-width: 480px; padding: .5em; box-sizing: border-box; }
	.qscf-form textarea { min-height: 5em; }
	.qscf-form .qscf-required { color: #c00; }
	.qscf-form .qscf-field.qscf-has-error input[type="text"], .qscf-form .qscf-field.qscf-has-error textarea { border: 1px solid #c00; }
	.qscf-form .qscf-field-error { display: block; margin-top: .25em; color: #9f3a38; font-size: .85em; }
	.qscf-form button { padding: .6em 1.6em; cursor: pointer; }
	.qscf-success-wrapper { padding: 1em; border: 1px solid #cce5cc; background: #f4faf4; border-radius: 4px; }
	.qscf-success-wrapper .qscf-thanks { font-weight: 600; }
	.qscf-submitted-table { border-collapse: collapse; margin-top: .5em; }
	.qscf-submitted-table th, .qscf-submitted-table td { border: 1px solid #ddd; padding: .4em .8em; text-align: left; }
	.qscf-submitted-table th { background: #f0f0f0; white-space: nowrap; }
	.qscf-submitted-table td { white-space: pre-wrap; }
	.qscf-error { padding: .8em 1em; border: 1px solid #e0b4b4; background: #fdf3f3; color: #9f3a38; border-radius: 4px; margin-bottom: 1em; }
	';
	wp_register_style( 'qscf-inline', false );
	wp_enqueue_style( 'qscf-inline' );
	wp_add_inline_style( 'qscf-inline', $css );
}
