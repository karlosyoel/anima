var count = 1;



_ws.onclose = function() { 
    setTimeout(function() {
        _ws = new WebSocket(host);
    }, 1000 * count++);
    // websocket is closed.
    console.log("Connection is closed..."); 
};

export {_ws}