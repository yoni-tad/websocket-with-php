const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const axios = require('axios');
const path = require('path');

const app = express();
const server = http.createServer(app);
const io = socketIo(server);

app.use(express.static(path.join(__dirname, 'public')));

io.on('connection', (socket) => {
    console.log('A user connected');

    // Fetch existing comments from PHP API and send to the newly connected client
    axios.get('http://localhost/test/websocket/node/api.php')
        .then(response => {
            response.data.forEach(comment => {
                socket.emit('comment', `${comment.name}: ${comment.message}`);
            });
        })
        .catch(error => {
            console.error('Error fetching comments:', error);
        });

    // Handle new comment from the client
    socket.on('comment', (msg) => {
        const [name, message] = msg.split(':');
        axios.post('http://localhost/test/websocket/node/api.php', `name=${encodeURIComponent(name)}&message=${encodeURIComponent(message)}`, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        })
        .then(response => {
            if (response.data.success) {
                io.emit('comment', msg); // Broadcast new comment to all clients
            } else {
                console.error('Error adding comment:', response.data.error);
            }
        })
        .catch(error => {
            console.error('Error posting comment:', error);
        });
    });

    socket.on('disconnect', () => {
        console.log('User disconnected');
    });
});

server.listen(8081, () => {
    console.log('Server is listening on port 8081');
});
