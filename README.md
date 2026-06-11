![ClearView Logo](/assets/Clearview-Icon-CIR-256.png)

## ClearView
------------

### 1 Introduction

#### 1.1 What Is It?

ClearView is a server-side framework for building dynamic web applications
without a build step. All generated output is designed to be human readable for
easy debugging.

It is structured as set of PHP classes that merge ProcessWire, HTMX, Surreal,
and PicoCSS. ProcessWire handles all the backend tasks, complete with role
based access control and a full admin interface. All code runs under the
permission of the logged in user and all data access goes through ProcessWire’s
data abstraction layer. ProcessWire interfaces are mostly abstracted using the
Crystal interface.

ClearView let's you seamlessly mix it's DOM-stored "Shard" variables with
ProcessWire's Page API as well as more "static" variables you can store in the
user's session (survives page reloads).  Element's update your screen when they
change and disappear from the screen when deleted.  A single call to setVars()
can change multiple elements in 1 call including changing CSS classes, adding
javascript, or other manipulations.  CSS classes are kept in sync between the
client and server.

### 2 System Stack

*   Nginx - caching web server
*   PHP-FPM - soon to switch to Nginx Unit application server
*   MariaDB - other options possible (see ProcessWire docs)
*   ProcessWire - PHP Backend, manages MariaDB for us & more
*   PicoCSS - main/global CSS, small and light theme engine
*   HTMX - for both Ajax calls and basic javascript
*   Surreal by Gnat - allows javascript to be encapsulated in the element
*   Gnat’s CSS companion for Surreal - encapsulates CSS in the element
*   InteractJs - [WIP] for consistent touch & gesture control
*   Phim - [WIP] PHP Color manipulation library - https://github.com/Talesoft/phim
*   ClearView - glues it all together

### 3 Examples

#### 3.1 Login Form

Here is an example of an "inlay" you would write to handle a simple login form.

```
<?php
namespace ClearView\\Form;
use ClearView\\Form;
class login extends Form
{
    // logout
    public function logout()
    {
        ClearView::Session()->logout();
        $this->triggerevent(’userchange’);
        echo "Login";           // Overwrites button text
    }
    // Processwire does all the login magic
    public function login()
    {
        if (ClearView::Session()->trylogin()) {
            $this->setVars([
            // Login successful
                ’formtitle’   => ’Login Succeeded!’,
                ’forminfo’    => ’Welcome back<br>{{text20\\User::displayname}}!’,
                ’login’       => ’Success!’
            ]);
            $this->triggerevent(’userchange’);
            $this->close();            // close the form
        } else {
            $this->setVars([           // Login failed
                ’formtitle’   => "Login Failed!",
                ’forminfo’    => "Try again, or reset your password using the link below",
                ’login’       => ’Try Again!’
            ]);
        }
    }
    // end class
}
```

### Getting Started.

You can start with the overview document in /docs. This is a design reference 
and will be replaced with real documentation at a later point.  You can also 
read some of the auto-generated API docs.  Hopefully, there will be some decent
tutorials available soon!

