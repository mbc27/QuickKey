"""生成站点图标：深色圆角底 + 键帽图形（与站点配色一致）
输出：site/assets/favicon.ico、favicon.svg、apple-touch-icon.png
可重复运行，改色改形只需调整下方常量。
"""
import os
from PIL import Image, ImageDraw

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(BASE, "site", "assets")

INK = (47, 43, 36, 255)       # 主色：墨
CREAM = (242, 237, 226, 255)  # 键帽白
ACCENT = (201, 111, 74, 255)  # 强调：陶土橙


def draw_icon(size: int) -> Image.Image:
    S = size * 4  # 4 倍超采样后缩小，边缘更平滑
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)

    # 圆角深色底
    d.rounded_rectangle([0, 0, S - 1, S - 1], radius=int(S * 0.22), fill=INK)

    # 键盘外框
    lw = max(2, int(S * 0.045))
    d.rounded_rectangle(
        [int(S * 0.15), int(S * 0.24), int(S * 0.85), int(S * 0.76)],
        radius=int(S * 0.075), outline=CREAM, width=lw,
    )

    def key(x0, y0, x1, y1, color):
        d.rounded_rectangle(
            [int(S * x0), int(S * y0), int(S * x1), int(S * y1)],
            radius=int(S * 0.022), fill=color,
        )

    # 第一排 4 键
    for i, x in enumerate((0.22, 0.375, 0.53, 0.685)):
        key(x, 0.325, x + 0.095, 0.415, ACCENT if i == 0 else CREAM)
    # 第二排 3 键 + 返回键
    for x in (0.22, 0.375, 0.53):
        key(x, 0.465, x + 0.095, 0.555, CREAM)
    key(0.685, 0.465, 0.78, 0.555, ACCENT)
    # 空格键
    key(0.30, 0.605, 0.70, 0.685, CREAM)

    return img.resize((size, size), Image.LANCZOS)


def main():
    os.makedirs(OUT, exist_ok=True)

    big = draw_icon(512)
    big.save(os.path.join(OUT, "favicon.ico"), sizes=[(16, 16), (32, 32), (48, 48), (64, 64)])
    big.resize((180, 180), Image.LANCZOS).save(os.path.join(OUT, "apple-touch-icon.png"))
    big.resize((192, 192), Image.LANCZOS).save(os.path.join(OUT, "favicon-192.png"))

    svg = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
  <rect width="64" height="64" rx="14" fill="#2f2b24"/>
  <rect x="9.6" y="15.4" width="44.8" height="33.2" rx="4.8" fill="none" stroke="#f2ede2" stroke-width="2.9"/>
  <rect x="14.1" y="20.8" width="6.1" height="5.8" rx="1.4" fill="#c96f4a"/>
  <rect x="24" y="20.8" width="6.1" height="5.8" rx="1.4" fill="#f2ede2"/>
  <rect x="33.9" y="20.8" width="6.1" height="5.8" rx="1.4" fill="#f2ede2"/>
  <rect x="43.8" y="20.8" width="6.1" height="5.8" rx="1.4" fill="#f2ede2"/>
  <rect x="14.1" y="29.8" width="6.1" height="5.8" rx="1.4" fill="#f2ede2"/>
  <rect x="24" y="29.8" width="6.1" height="5.8" rx="1.4" fill="#f2ede2"/>
  <rect x="33.9" y="29.8" width="6.1" height="5.8" rx="1.4" fill="#f2ede2"/>
  <rect x="43.8" y="29.8" width="6.1" height="5.8" rx="1.4" fill="#c96f4a"/>
  <rect x="19.2" y="38.7" width="25.6" height="5.1" rx="1.4" fill="#f2ede2"/>
</svg>
"""
    with open(os.path.join(OUT, "favicon.svg"), "w", encoding="utf-8") as f:
        f.write(svg)

    print("done:", OUT)


if __name__ == "__main__":
    main()
