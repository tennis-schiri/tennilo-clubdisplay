"""Measure the predominant neutral background around the Allianz logo."""
from collections import Counter
from PIL import Image
import sys

with Image.open(sys.argv[1]) as source:
    source.load()
    im = source.convert("RGB")
w, h = im.size
if w < 10 or h < 10:
    raise ValueError("Invalid logo dimensions")
# Sample the outer two pixel rows/columns, excluding coloured design elements.
pixels = [im.getpixel((x,y)) for y in range(h) for x in range(w)
          if x < 2 or y < 2 or x >= w-2 or y >= h-2]
neutral = [p for p in pixels if min(p) >= 160 and max(p)-min(p) <= 15]
if len(neutral) < len(pixels)*0.2:
    raise ValueError("No reliable light-grey background")
# Group slight JPEG compression variation, then retain the most common actual RGB.
groups = Counter(tuple(v//8 for v in p) for p in neutral)
group = groups.most_common(1)[0][0]
rgb = Counter(p for p in neutral if tuple(v//8 for v in p)==group).most_common(1)[0][0]
print("#%02x%02x%02x" % rgb)
