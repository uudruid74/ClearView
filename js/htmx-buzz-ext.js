htmx.defineExtension('buzz', {
    init: function() {
        if ('vibrate' in navigator) {
            if (typeof htmx.config.buzzEnabled === 'undefined') {
                htmx.config.buzzEnabled = true;
            }
            console.log('Buzz extension loaded, buzzEnabled:', htmx.config.buzzEnabled);
        } else {
            console.log('Vibration API not supported');
        }
    },

    onEvent: function(name, evt) {
        if (name !== 'htmx:trigger') return;
        console.log("Event type: ", evt.type);
        if (!htmx.config.buzzEnabled) {
            console.log('Buzz disabled');
            return;
        }
        let elt = evt.target || evt.srcElement;
        let buzzAttr = elt.getAttribute('hx-buzz');
        // Climb the DOM if no hx-buzz on the clicked element
        while (!buzzAttr && elt.parentElement) {
            elt = elt.parentElement;
            buzzAttr = elt.getAttribute('hx-buzz');
        }
        if (!buzzAttr) {
            console.log("Can't find a buzz");
            return;
        }
        const durations = buzzAttr.split(',')
            .map(n => parseInt(n.trim(), 10))
            .filter(n => !isNaN(n) && n > 0);
        console.log("BUZZ! ", durations);
        (async () => {navigator.vibrate(durations)})();
    }

});
