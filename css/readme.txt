Link these files into your web directory.

Currently cv.css and clearview.css are different files so I can modify cv.css
easier and not rebuild the minimized versions.  cv.css stabilizes more:

clearview.css = pico.css + cv.css
remove the line loading cv.css in views/Default/css_files.php

