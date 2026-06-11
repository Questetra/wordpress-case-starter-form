# WordPress Case Starter Form (Reference)

WordPress のフォームから Questetra BPM Suite の **メッセージ開始イベント (HTTP)** を起動（ケースを開始）するための、**リファレンス実装**の WordPress プラグインです。

> [!IMPORTANT]
> これは「動かし方」を示すための**リファレンス実装**であり、**公式プラグインではありません**。
> MIT ライセンスの**無保証**ソフトウェアで、**サポート対象外**です。本番利用の際は内容を理解した上で、自己責任でご利用ください。

## これで学べること / デモすること

- WordPress の固定ページ・投稿に、ショートコード `[qscf_form]` でフォームを埋め込む方法
- **サーバサイド送信**で開始イベントの URL と API Key を**ページソースに露出させない**方法（CORS 不要）
- **ファイル（複数添付）を multipart/form-data で送る**方法
- 送信完了画面（サンクス＋送信内容）の表示（Post/Redirect/Get パターン）
- **バリデーションエラーをフィールドごとに表示**する方法（Questetra が返すフィールド別エラーの振り分けを含む）

## 動作要件

- 独自プラグインを設置できる WordPress 環境（自前ホスト等）。PHP 7.4+ / WordPress 6.x で確認。
- **WordPress.com の無料・Personal・Premium プランでは独自プラグインを設置できません**（Business 以上が必要）。手元で試すだけなら、本リポジトリ同梱の `example-docker/` か、ローカル WordPress（Studio / Local / wp-now 等）が手軽です。

## インストール

1. `questetra-case-starter-form/` フォルダを `wp-content/plugins/` に置く（または ZIP 化して管理画面からアップロード）。
2. 管理画面 → プラグイン → 「**Questetra Case Starter Form (Reference)**」を有効化。
3. 固定ページ／投稿の本文にショートコードを貼る:

   ```
   [qscf_form]
   ```

## 設定

`questetra-case-starter-form/questetra-case-starter-form.php` 冒頭の **設定ブロックだけ**を編集します（その下のロジック本体は編集不要）。

- `QSCF_ENDPOINT_URL` … 対象の開始イベントの起動 URL（末尾の `?key=` は付けない）
- `QSCF_API_KEY` … API Key。**プレースホルダのままにせず、自分のキーに置き換えてください。**
  公開リポジトリ等に載せる場合は直書きを避け、`wp-config.php` に
  `define( 'QSCF_API_KEY', 'xxxx' );` と定義する形を推奨します。
- `QSCF_FIELDS` … フォーム項目（＝受信パラメータ）の定義。増減で任意の構成に対応:

  ```php
  define( 'QSCF_FIELDS', array(
      array( 'param' => 'title',     'label' => '件名',       'type' => 'text',     'required' => false ),
      array( 'param' => 'q_string0', 'label' => '文字単一行', 'type' => 'text',     'required' => true ),
      array( 'param' => 'q_string1', 'label' => '文字複数行', 'type' => 'textarea', 'required' => true ),
      array( 'param' => 'q_file11',  'label' => 'ファイル',   'type' => 'file',     'required' => true ),
  ) );
  ```

  | キー | 説明 |
  |------|------|
  | `param` | Questetra の受信パラメータ名（半角英数字・`_` のみ。`action` / `qscf_*` と重複させない） |
  | `label` | 画面に表示するラベル |
  | `type` | `text`（文字1行）/ `textarea`（文字複数行）/ `file`（ファイル） |
  | `required` | `true` で必須（text/textarea は入力必須、file は「添付そのものが必須」） |

  ※ ファイルは常に複数添付可です。**個数の上限・下限（○個以上 等）は Questetra 側の項目設定が検証**し、返ってきたエラーは該当フィールドの直下に表示されます。

## 仕組み（概要）

1. ショートコードがフォームを描画。送信は `admin-post.php` 経由の**サーバサイド処理**に向くため、URL と API Key はブラウザに渡りません。
2. ハンドラが入力を検証し、`QSCF_FIELDS` を基に multipart/form-data を組み立てて、サーバから Questetra の開始イベントへ POST します（ファイルは `param` 名で繰り返し送信）。
3. 成功時は送信内容を一時保存し、**元ページへリダイレクト**（Post/Redirect/Get）。戻り先ページのショートコードがサンクス画面と送信内容、起動した**ケースID**を表示します。
4. 失敗時は、フィールド別のエラー（プラグインの必須チェック、または Questetra が返す `<key>` 付きエラー）を各入力欄の下に表示し、入力値を保持して再表示します。

## ローカルでの動作確認（任意）

`example-docker/` で最小の WordPress を起動できます（Docker 必要）。

```
cd example-docker
docker compose up -d
# http://localhost:8080 で初期セットアップ → プラグイン有効化 → 固定ページに [qscf_form]
```

※ 送信を成功させるには、プラグインの `QSCF_API_KEY` / `QSCF_ENDPOINT_URL` / `QSCF_FIELDS` を自分の開始イベントに合わせて設定してください。

## ライセンス

MIT License. 詳細は [LICENSE](./LICENSE) を参照。
