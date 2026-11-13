# 速键库 · 专业软件快捷键知识库

**[简体中文](README.md) | [English](README.en.md)**

![CI](https://github.com/mbc27/QuickKey/actions/workflows/ci.yml/badge.svg)

在线体验：**https://xcsy.my-place.us/**

一个纯 PHP + JSON 文件存储的软件快捷键速查网站。无需数据库、无需框架、无需注册登录，上传到任何支持 PHP 的虚拟主机即可运行。

覆盖办公、设计、开发、BIM、GIS、工控等行业软件，收录 **2000+ 条快捷键**，支持 Windows / Mac / Linux 平台切换、多版本查询、功能关键词搜索、一键复制、本地收藏与软件对比。

## 功能特性

- **快捷键速查**：按软件浏览，快捷键按功能分组，表格展示
- **平台 / 版本切换**：同一软件支持 Windows / Mac / Linux 与多版本快捷键对照
- **功能搜索**：直接搜"图层""截图""对齐"等功能词，不必知道菜单在哪
- **一键复制**：点击任意快捷键即复制，方便写文档、教新人
- **收藏夹**：常用软件本地收藏（localStorage，无账号）
- **软件对比**：两款软件快捷键并排对照，跨软件迁移习惯
- **热门排行**：基于真实访问量统计（PV/UV 双口径）
- **纠错与投稿**：访客提交进后台人工审核，支持防刷限流
- **管理后台**：数据看板（访客 IP、14 天柱状图）、软件/快捷键增删改查、投稿管理、公告、双二维码（微信/QQ）配置

## 技术栈

- PHP 8.x（无框架、无数据库、无 Composer 依赖）
- JSON 文件存储（原子写入 + 自动备份）
- 原生 JavaScript / CSS（无构建步骤）

## 快速开始

### 本地运行

```bash
# 方式一：PHP 内置服务器
cd site
php -S localhost:8080

# 方式二：phpStudy / XAMPP
# 把 site 目录（或仓库根目录）设为网站根目录即可
```

打开 `http://localhost:8080` 即可访问。

### 部署到虚拟主机（cPanel / 宝塔 / phpStudy 等）

1. 上传仓库全部文件到网站根目录（如 `htdocs` / `wwwroot`）；
2. 确认 PHP ≥ 8.0，启用 `mbstring`、`json`、`session` 扩展；
3. 给 `site/data/`（若单目录部署则为 `data/`）目录**写权限**（755）；
4. Apache 环境自动生效 `data/.htaccess`（禁止 Web 访问数据）；Nginx 需手动加：
   ```nginx
   location ^~ /data/ { deny all; return 404; }
   ```
5. 后台入口 `admin.php`，默认账号 `admin / admin123456`，**登录后请立即修改密码**。

## 管理后台

| 功能 | 说明 |
|---|---|
| 数据看板 | 今日/累计浏览量与访客（10 分钟会话去重）、访客 IP 列表与明细、最近 14 天柱状图 |
| 软件管理 | 软件与快捷键增删改查、待补充清单搜索/收录/移除 |
| 投稿管理 | 查看访客纠错与投稿（含提交者 IP）、标记处理、删除，菜单显示待处理数 |
| 设置 | 站点公告、QQ/微信号与二维码图片上传（支持在线裁剪）、修改密码 |

后台带登录防爆破（5 次失败锁 15 分钟）、CSRF 校验、一次性入口令牌等防护。

## 数据文件说明

快捷键数据为 JSON，首次部署自动兼容两种位置（站点同级或上级目录）：

- `softwares.json` — 已收录软件与快捷键
- `pending.json` — 待补充软件清单

`softwares.json` 核心结构：

```json
{
  "app_hotkeys": [
    {
      "app_name": "Photoshop",
      "platform": "Windows",
      "version": "2024",
      "category": "设计",
      "tags": ["图像"],
      "aliases": ["PS", "Ps"],
      "doc_url": "https://helpx.adobe.com/photoshop/keyboard-shortcuts.html",
      "hotkeys": [
        { "keys": "Ctrl+J", "desc": "复制图层", "group": "图层" }
      ]
    }
  ]
}
```

后台「软件管理」支持可视化编辑，也可以直接编辑 JSON 批量导入。

## 目录结构

```
├── site/                  # 网站根（可单独部署此目录）
│   ├── index.php          # 前台（首页/分类/详情/搜索/收藏/对比/投稿/关于/API）
│   ├── admin.php          # 管理后台
│   ├── lib.php            # 数据层与公共函数
│   ├── sitemap.php        # 动态站点地图
│   ├── assets/            # 样式/脚本/图标（assets/qr/ 为二维码上传目录）
│   └── data/              # 运行数据（部署后自动生成，已 gitignore）
├── 清单.json              # 快捷键主数据（softwares.json 的开发目录副本）
├── 待补充清单.json        # 待补充数据
├── tools/                 # 辅助脚本（语法校验/图标生成/打包）
└── docs/                  # 开发文档
```

## 安全说明

- `data/` 目录存放账号哈希、配置、统计与投稿数据，已通过 `.htaccess` 禁止 Web 访问，Nginx 用户请参照上文补规则；
- 密码使用 `password_hash` 存储；所有输出经 HTML 转义；写操作带 CSRF 校验；
- 未登录接口限流（30 次/分钟）、投稿限流（5 条/10 分钟）、访客日志防刷（15 次/分钟）；
- 本仓库不包含任何真实运行数据（二维码、访客 IP、投稿内容、密码哈希均已排除）。

## CI

每次 push / PR 自动运行 GitHub Actions：

- PHP 8.1 / 8.2 / 8.3 语法检查（`php -l` 全量文件）
- 数据 JSON 有效性校验
- 冒烟测试：用 PHP 内置服务器启动站点，断言首页、搜索页、JSON API、后台登录页均可正常渲染

## License

[MIT](LICENSE)
