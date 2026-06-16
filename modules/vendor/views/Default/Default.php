<html lang="en" data-theme="dark">
<head> </head>
<body class="container">
<header class="cramped glass vertical container-fluid">
    <layout view="formheader">
        <aside name="icon"><icon src="/pics/Clearview-Icon-CIR-256.png"> </icon></aside>
        <hgroup class="centered rows" style="margin-top:0.25rem; margin-bottom:0;">
            <aside name="headline"><h2 id="headline">{{Page::headline}}</h2></aside>
            <aside name="summary"><div id="summary">{{Page::summary}}</div></aside>
        </hgroup>
    </layout>
</header>

<layout class="horizontal spread">
    <layout id="navbar" class="vertical nav-drawer"> </layout>
    <layout class="columns">
        <main hx-target="{{Config::id_main_body}}" page-fields="summary sidebar headline">
            <article id="{{Config::id_main_body}}" class="glass container-fluid">
                {{Page::body}}
            </article>
	    <aside id="sidebar" style="width:50%;" class="landscape vertical sidebar glass container-fluid"> {{Page::sidebar}} </aside>
        </main>
    </layout>
</layout>

<footer class="centered glass container-fluid"> </footer>
</body>
</html>
