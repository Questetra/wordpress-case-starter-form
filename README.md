# WordPress Case Starter Form (Reference)

WordPress のフォームから Questetra BPM Suite の **メッセージ開始イベント (HTTP)** を起動（ケースを開始）するための、**リファレンス実装**の WordPress プラグインです。

> [!IMPORTANT]
> これは「動かし方」を示すための**リファレンス実装**であり、**公式プラグインではありません**。
> MIT ライセンスの**無保証**ソフトウェアで、**サポート対象外**です。本番利用の際は内容を理解した上で、自己責任でご利用ください。

## これで学べること / デモすること

- **preset レジストリ方式**で、1インストールのまま複数の開始イベント（フォーム）を異なるページで出し分ける方法
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
   [qscf_form preset="contact"]
   ```

## 設計上の不変条件

**秘密と接続先（endpoint / key）は preset レジストリ＝サーバ側に残し、コンテンツ層（ショートコード属性）に渡るのは preset 名だけ。**

- `endpoint`（URL）と `key`（API Key）はショートコード属性で指定できません。
- ブラウザには preset 名だけが渡り、サーバ側でレジストリから実値を引きます。

## 設定

`questetra-case-starter-form/questetra-case-starter-form.php` 冒頭の **`QSCF_PRESETS` 定義ブロックだけ**を編集します。配布ファイルは同一のため行番号も固定で、**33 行目の `define( 'QSCF_PRESETS', array(`** から始まるブロックがそれです（**70 行目の `■■■ これより下はロジック本体 ■■■`** より下は編集不要）。

### 1. QSCF_PRESETS を設定する

```php
define( 'QSCF_PRESETS', array(

    // preset 名 'contact'（問い合わせフォーム）
    'contact' => array(
        'endpoint'       => 'https://your-tenant.questetra.net/.../start',
        'key'            => 'PUT-YOUR-API-KEY-HERE',  // 自分の API キーに置き換え
        'fields'         => array(
            array( 'param' => 'title',     'label' => '件名',       'type' => 'text',     'required' => false ),
            array( 'param' => 'q_string0', 'label' => 'お名前',     'type' => 'text',     'required' => true ),
            array( 'param' => 'q_string1', 'label' => 'お問い合わせ内容', 'type' => 'textarea', 'required' => true ),
        ),
        'thanks'         => 'お問い合わせありがとうございました。',  // 任意。省略時はデフォルト文言
        'max_file_bytes' => 10 * 1024 * 1024,                        // 任意。省略時は 10MB
    ),

    // preset 名 'apply'（申請フォーム）
    'apply' => array(
        'endpoint'       => 'https://your-tenant.questetra.net/.../start',
        'key'            => 'PUT-YOUR-API-KEY-HERE',
        'fields'         => array(
            array( 'param' => 'title',    'label' => '申請タイトル', 'type' => 'text',     'required' => true ),
            array( 'param' => 'q_file11', 'label' => '添付書類',     'type' => 'file',     'required' => true ),
        ),
    ),

) );
```

**API キーについて**

`key` には API キーを直接記述します（`PUT-YOUR-API-KEY-HERE` を自分のキーに置き換え）。送信はサーバサイドで行うためページソースには出ません。プラグインを **private リポジトリで管理する前提**（`wp-config.php` を編集できない WordPress.com 等を想定）なので、直書きで問題ありません。

> （おまけ）`wp-config.php` を編集できる環境では、そこにキーを定義し `'key' => defined( 'QSCF_CONTACT_KEY' ) ? QSCF_CONTACT_KEY : '',` のように参照してキーを分離することもできます（public リポジトリで管理する場合など）。

#### fields の各キー

| キー | 説明 |
|------|------|
| `param` | Questetra の受信パラメータ名（例: `title` / `q_string0` / `q_file11`） |
| `label` | 画面に表示するラベル |
| `type` | `text`（文字1行）/ `textarea`（文字複数行）/ `file`（ファイル） |
| `required` | `true` で必須（text/textarea は入力必須、file は「添付そのものが必須」） |

※ ファイルは常に複数添付可です。**個数の上限・下限（○個以上 等）は Questetra 側の項目設定が検証**し、返ってきたエラーは該当フィールドの直下に表示されます。

### 2. ショートコードを貼る

```
[qscf_form preset="contact"]
[qscf_form preset="apply"]
[qscf_form preset="contact" thanks="カスタムメッセージ"]
```

- `preset` … 使用する preset 名（必須。未指定時は `"default"` を探し、なければ管理者向けの注意を表示）
- `thanks` … 送信成功メッセージの上書き（任意。`endpoint`・`key`・`fields` はショートコード属性から指定不可）

## 仕組み（概要）

1. ショートコードがフォームを描画。フォームには preset 名を hidden フィールドで埋め込み、送信は `admin-post.php` 経由の**サーバサイド処理**に向くため、URL と API Key はブラウザに渡りません。
2. ハンドラが `qscf_preset` をホワイトリスト検証し、`QSCF_PRESETS` からその preset の `endpoint`・`key`・`fields` を取得。入力を検証して multipart/form-data を組み立て、サーバから Questetra の開始イベントへ POST します（ファイルは `param` 名で繰り返し送信）。
3. 成功時は送信内容を一時保存し、**元ページへリダイレクト**（Post/Redirect/Get）。戻り先ページのショートコードがサンクス画面と送信内容、起動した**ケースID**を表示します。transient は preset 名に紐づけるため、同一ページに複数フォームがあっても取り違えません。
4. 失敗時は、フィールド別のエラー（プラグインの必須チェック、または Questetra が返す `<key>` 付きエラー）を各入力欄の下に表示し、入力値を保持して再表示します。

## ローカルでの動作確認（任意）

`example-docker/` で最小の WordPress を起動できます（Docker 必要）。

```
cd example-docker
docker compose up -d
# http://localhost:8080 で初期セットアップ → プラグイン有効化 → 固定ページに [qscf_form preset="contact"]
```

※ 送信を成功させるには、プラグインの `QSCF_PRESETS`（`endpoint` / `key` / `fields`）を自分の開始イベントに合わせて設定してください。

## ライセンス

MIT License. 詳細は [LICENSE](./LICENSE) を参照。
