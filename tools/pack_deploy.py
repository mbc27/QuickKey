"""打包部署 zip：site 目录 + 根数据 JSON，路径统一用 /，避免主机解压出 assets\\xxx 这类怪名。
用法：python tools/pack_deploy.py
输出：deploy/quickkey-site.zip
"""
import os
import zipfile

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = os.path.join(BASE, "site")
OUT = os.path.join(BASE, "deploy", "quickkey-site.zip")

# data 目录只带必要文件（不含本地统计数据/登录锁定/旧二维码）
DATA_KEEP = {
    ".htaccess",
    "admin_config.php",
    "site_config.json",
    "submissions.json",
}
# 明确不打包的根文件（开发/部署用，站点不需要）
ROOT_SKIP = {"deploy", "tools", "site", "README.md", "软件清单.md", "nginx.htaccess"}


def dirs_in_zip(zf, folders):
    for f in folders:
        zi = zipfile.ZipInfo(f + "/")
        zi.external_attr = (0o40755 << 16) | 0x10
        zf.writestr(zi, "")


def main():
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    if os.path.exists(OUT):
        os.remove(OUT)

    with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as zf:
        dirs_in_zip(zf, ["assets", "assets/qr", "data"])

        # 1) site 目录下的所有文件
        for root, dirs, files in os.walk(SITE):
            dirs[:] = [d for d in dirs if d not in ("backups",)]
            for name in files:
                full = os.path.join(root, name)
                rel = os.path.relpath(full, SITE).replace("\\", "/")
                if rel.startswith("data/") and os.path.basename(rel) not in DATA_KEEP:
                    continue
                if rel.endswith(".tmp"):
                    continue
                zf.write(full, rel)

        # 2) 根目录资源
        zf.write(os.path.join(BASE, "favicon.ico"), "favicon.ico")
        zf.write(os.path.join(BASE, "deploy", "stage", ".htaccess"), ".htaccess")

        # 3) 数据 JSON（改成 ASCII 文件名，避免中文名在主机解压/URL 上出问题）
        zf.write(os.path.join(BASE, "清单.json"), "softwares.json")
        zf.write(os.path.join(BASE, "待补充清单.json"), "pending.json")

    size = os.path.getsize(OUT)
    with zipfile.ZipFile(OUT) as zf:
        names = zf.namelist()
    print(f"OK -> {OUT}  ({size/1024:.0f} KB, {len(names)} entries)")
    for n in sorted(names):
        print("  ", n)


if __name__ == "__main__":
    main()
