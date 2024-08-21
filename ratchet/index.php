<!DOCTYPE html>
<html>
<head>
    <title>Ratchet WebSocket Example</title>
</head>
<body>
    <h1>WebSocket with Ratchet</h1>

    <h2>Add Comment</h2>
    <input type="text" id="name" placeholder="Name">
    <textarea id="message" placeholder="Message"></textarea>
    <button onclick="addComment()">Add Comment</button>

    <h2>Comments</h2>
    <ul id="commentsList"></ul>

    <script>
        var ws = new WebSocket('ws://localhost:8081');

        ws.onopen = function() {
            console.log('Connected to WebSocket server');
            fetchComments();
        };

        ws.onmessage = function(event) {
            try {
                var message = JSON.parse(event.data);
                if (message.action === 'data_list') {
                    updateCommentsList(message.data);
                }
            } catch (error) {
                console.error('Error parsing WebSocket message:', error);
            }
        };

        function addComment() {
            var name = document.getElementById('name').value;
            var message = document.getElementById('message').value;
            ws.send(JSON.stringify({
                action: 'add_data',
                table: 'comments',
                data: { name: name, message: message }
            }));
        }

        function fetchComments() {
            fetch('api.php?action=get_data&table=comments')
                .then(response => response.json())
                .then(data => updateCommentsList(data))
                .catch(error => console.error('Error fetching comments:', error));
        }

        function updateCommentsList(comments) {
            var commentsList = document.getElementById('commentsList');
            commentsList.innerHTML = '';
            comments.forEach(comment => {
                var listItem = document.createElement('li');
                listItem.textContent = `${comment.name}: ${comment.message}`;
                commentsList.appendChild(listItem);
            });
        }
    </script>
</body>
</html>
