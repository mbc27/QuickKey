# 参与贡献

感谢你愿意帮速键库补充内容或改进代码。这个项目靠「大家发现一处错、补一条快捷键」慢慢变完整。

## 一、只想补 / 改快捷键（不需要懂代码）

**推荐方式：站内投稿**

打开 [速键库](https://xcsy.my-place.us/index.php?p=feedback)，填写软件名称、正确内容、参考来源后提交。站长会在后台逐条审核，采纳后直接写进主数据。

**或者在 GitHub 提 Issue**

选择「快捷键纠错 / 补充」模板，填清楚：

- 软件名称
- 平台 / 版本（Windows 10、macOS 14、2024 版……快捷键常常不一样）
- 网站上显示的内容（如果是改错）
- 正确的内容
- 参考来源（官方文档链接最好）

## 二、提交代码（Pull Request）

### 环境要求

- PHP ≥ 8.0（推荐 8.1 / 8.2 / 8.3），启用 `mbstring`、`json`、`session`
- 无需 Composer、无需 Node 构建

### 本地跑起来

```bash
git clone https://github.com/mbc27/QuickKey.git
cd QuickKey/site
php -S localhost:8080
```

打开 `http://localhost:8080`。数据文件在仓库根目录（`清单.json` / `待补充清单.json`），首次访问后台会自动生成 `data/admin_config.php`（默认 `admin / admin123456`）。

### 提交前自检

```bash
# 1. PHP 语法（也可直接用 php -l）
python tools/check_php.py site/lib.php site/index.php site/admin.php

# 2. JS 语法
node --check site/assets/app.js
node --check site/assets/crop.js

# 3. 数据 JSON 合法性
python -m json.tool 清单.json > /dev/null
```

CI 会在 PR 上自动跑同样的检查（PHP 8.1/8.2/8.3 语法 + JSON 校验 + 起站冒烟测试），全绿再合并。

### 代码约定

| 项目 | 约定 |
|---|---|
| 语言 | PHP 8 + 原生 JS，**不引入框架、不引入构建工具、不加 Composer 依赖** |
| 存储 | 只用 JSON 文件 + `write_json_file()` 原子写入，不引数据库 |
| 输出 | 所有动态内容必须经 `e()` 转义；`htmlspecialchars` 不要裸用 |
| 写操作 | 必须带 CSRF 校验（`csrf_field()` / `check_csrf()`） |
| 输入 | 文本走 `clean_text()` 截断，URL 走 `filter_var(..., FILTER_VALIDATE_URL)` |
| 样式 | 复用 `style.css` 里的 CSS 变量（`--ink` `--primary` `--accent`），不写死颜色 |
| 兼容 | 改动后请确认「手机 375px / 平板 768px / 桌面 1440px」三档不破版 |

### 数据格式（直接改 JSON 时）

```json
{
  "app_hotkeys": [
    {
      "app_name": "Photoshop",
      "platform": "Windows",
      "version": "2024",
      "category": "设计",
      "tags": ["图像"],
      "aliases": ["PS"],
      "doc_url": "https://helpx.adobe.com/photoshop/keyboard-shortcuts.html",
      "hotkeys": [
        { "keys": "Ctrl+J", "desc": "复制图层", "group": "图层" }
      ]
    }
  ]
}
```

- 同一个软件可以有多条记录（不同平台 / 版本），前台会自动聚合到一张详情页；
- `group` 用于详情页分组小标题，留空则归到「常规」；
- 千万不要改 `app_name` 的写法（全站按名字聚合）；改名前请先在 Issue 里沟通。

## 三、不要提交的东西

以下文件属于个人运行数据，已在 `.gitignore` 中，**PR 里请勿包含**：

- `site/data/*.json`（配置、统计、投稿、限流记录）
- `site/data/admin_config.php`（密码哈希）
- `site/assets/qr/*`（个人二维码）
- 任何访客 IP、联系方式、后台账号

## 四、行为准则

- 只提交你有权公开的内容，快捷键请勿整段照抄受版权保护的付费文档，**以「功能 + 按键」的事实描述方式整理**；
- 发现安全问题请**不要公开提 Issue**，直接邮件联系仓库维护者；
- 讨论就事论事，不人身攻击。

## 五、License

提交 PR 即表示你同意以 [MIT](LICENSE) 协议贡献该部分内容。
