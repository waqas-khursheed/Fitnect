const express = require('express');
const app = express();
var fs = require('fs');
const options = {
    key: fs.readFileSync('/etc/letsencrypt/live/admin.fitnect.app/privkey.pem'),
    cert: fs.readFileSync('/etc/letsencrypt/live/admin.fitnect.app/fullchain.pem'),
};
const server = require('https').createServer(options, app);
// const server = require('http').createServer(app);

var io = require('socket.io')(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST", "PATCH", "DELETE"],
        credentials: true,
        transports: ['websocket', 'polling'],
        allowEIO3: false
    },
});

var mysql = require("mysql");

var con_mysql = mysql.createPool({
    host: "localhost",
    user: "fitnect_user",
    password: "TD@2^D0Mh]q0",
    database: "Fitnect",
    debug: true,
    charset: 'utf8mb4'
});

var FCM = require('fcm-node');
var serverKey = 'AAAAW4gumeY:APA91bHJR1UmDUoxEtrSQUM9In5TDW-nbISw0S_wMxcR9eNniPYn-ymZT6DzD_LfOm3b9z3E3V5qikrkjOTy0t-2vC1mM9NG97V6CbY98qrIGK4xnOnmtqhFvTAhdDKcuvO1fNHwV-yQ';
var fcm = new FCM(serverKey);


// SOCKET START
io.on('connection', function (socket) {
    console.log('socket connection *** ', socket.connected)

    // User Online
    const userId = socket.handshake.query.userId;
    con_mysql.query(`UPDATE users SET online_status = 'online' WHERE id = ?`, [userId], function (error) {

        if (error) {
            console.log("Error updating online status:", error);
        } else {
            console.log("User set to online:", userId);
        }
    });
    // GET MESSAGES EMIT

    socket.on('chat_list', function (object) {
        var user_room = "user_" + object.user_id;
        socket.leave(user_room);
        socket.join(user_room);

        chat_list(object, function (response) {
            if (response) {
                console.log("chat_list has been successfully executed...");
                io.to(user_room).emit('response', { object_type: "chat_list", data: response });
            } else {
                console.log("chat_list has been failed...");
                io.to(user_room).emit('error', { object_type: "chat_list", message: "There is some problem in chat_list..." });
            }
        });
    });

    socket.on('get_messages', function (object) {
        var sender_room = "user_" + object.sender_id;
        socket.join(sender_room);

        get_messages(object, function (response) {
            if (response) {
                console.log("get_messages has been successfully executed...");
                io.to(sender_room).emit('response', { object_type: "get_messages", data: response });
            } else {
                console.log("get_messages has been failed...");
                io.to(sender_room).emit('error', { object_type: "get_messages", message: "There is some problem in get_messages..." });
            }
        });
    });

    // SEND MESSAGE EMIT
    socket.on('send_message', function (object) {
        var sender_room = "user_" + object.sender_id;
        var receiver_room = "user_" + object.receiver_id;

        send_message(object, function (response) {
            if (response) {

                if (response[0]['device_token'] == null) {
                    io.to(sender_room).to(receiver_room).emit('response', { object_type: "get_message", data: response[0] });
                    console.log("Successfully sent with response: ");
                } else {
                    var full_name = response[0]['first_name'] + ' ' + response[0]['last_name'];
                    var message = { //this may vary according to the message type (single recipient, multicast, topic, et cetera)
                        to: response[0]['device_token'],
                        collapse_key: 'your_collapse_key',

                        notification: {
                            title: full_name + ' Sent you a message',
                            body: full_name + ' Sent you a message',
                            user_name: full_name,
                            notification_type: 'chat',
                            other_id: object.sender_id,
                            vibrate: 1,
                            sound: 1
                        },

                        data: {  //you can send only notification or only data(or include both)
                            title: full_name + ' Sent you a message',
                            body: full_name + ' Sent you a message',
                            user_name: full_name,
                            notification_type: 'chat',
                            other_id: object.sender_id,
                            vibrate: 1,
                            sound: 1
                        }
                    };

                    fcm.send(message, function (err, response_two) {
                        if (err) {
                            console.log("Something has gone wrong!");
                            io.to(sender_room).to(receiver_room).emit('response', { object_type: "get_message", data: response[0] });
                        } else {
                            io.to(sender_room).to(receiver_room).emit('response', { object_type: "get_message", data: response[0] });
                            console.log("Successfully sent with response: ", response_two);
                        }
                    });
                }
            } else {
                console.log("send_message has been failed...");
                io.to(sender_room).emit('error', { object_type: "get_message", message: "There is some problem in get_message..." });
            }
        });
    });

    socket.on('read_messages', async function (object) {
        try {
            var user_room = "user_" + object.sender_id;
            const response = await read_messages(object);

            if (response) {
                console.log("read_message success...");
                io.to(user_room).emit('response', { object_type: "read_messages" });
            } else {
                console.log("read_message failed error...");
            }

        } catch (err) {
            console.log("read_message error...", err);
        }
    });

    socket.on('is_typing', function () {
        var receiver_room = "user_" + object.receiver_id;
        io.to(receiver_room).emit('response', { object_type: object.type });
    });

    socket.on('disconnect', function () {
        console.log("Use disconnection", socket.id)

        // user Offline
        con_mysql.query(`UPDATE users SET online_status = 'offline' WHERE id = ?`, [userId], function (error) {
            if (error) {
                console.log("Error updating offline status:", error);
            } else {
                console.log("User set to offline:", userId);
            }
        });
    });
});
// SOCKET END

// GET MESSAGES FUNCTION
var chat_list = function (object, callback) {
    con_mysql.getConnection(function (error, connection) {
        if (error) {
            callback(false);
        } else {
            connection.query(`
            SELECT 
            user_id, 
            first_name, 
            last_name, 
            user_type, 
            profile_image, 
            message, 
            created_at, 
            type, 
            unread_count, 
            online_status
        FROM (
            (
                SELECT 
                    users.id AS user_id,
                    users.first_name,
                    users.last_name,
                    users.user_type,
                    users.profile_image,
                    users.online_status,
                    (
                        SELECT COUNT(id) 
                        FROM chats AS st 
                        WHERE st.sender_id = users.id 
                        AND st.receiver_id = ${object.user_id} 
                        AND st.read_at IS NULL
                    ) AS unread_count,
                    (
                        SELECT message 
                        FROM chats AS st 
                        WHERE (st.sender_id = ${object.user_id} AND st.receiver_id = users.id) 
                        OR (st.sender_id = users.id AND st.receiver_id = ${object.user_id}) 
                        ORDER BY id DESC LIMIT 1
                    ) AS message,
                    (
                        SELECT type 
                        FROM chats AS st 
                        WHERE (st.sender_id = ${object.user_id} AND st.receiver_id = users.id) 
                        OR (st.sender_id = users.id AND st.receiver_id = ${object.user_id}) 
                        ORDER BY created_at DESC LIMIT 1
                    ) AS type,
                    (
                        SELECT created_at 
                        FROM chats AS st 
                        WHERE (st.sender_id = ${object.user_id} AND st.receiver_id = users.id) 
                        OR (st.sender_id = users.id AND st.receiver_id = ${object.user_id}) 
                        ORDER BY created_at DESC LIMIT 1
                    ) AS created_at
                FROM chats
                LEFT JOIN users ON users.id = chats.sender_id
                WHERE chats.receiver_id = ${object.user_id} 
                AND chats.deleted_at IS NULL 
                AND chats.group_id IS NULL
            )
            UNION
            (
                SELECT 
                    users.id AS user_id,
                    users.first_name,
                    users.last_name,
                    users.user_type,
                    users.profile_image,
                    users.online_status,
                    (
                        SELECT COUNT(id) 
                        FROM chats AS st 
                        WHERE st.sender_id = users.id 
                        AND st.receiver_id = ${object.user_id} 
                        AND st.read_at IS NULL
                    ) AS unread_count,
                    (
                        SELECT message 
                        FROM chats AS st 
                        WHERE (st.sender_id = ${object.user_id} AND st.receiver_id = users.id) 
                        OR (st.sender_id = users.id AND st.receiver_id = ${object.user_id}) 
                        ORDER BY id DESC LIMIT 1
                    ) AS message,
                    (
                        SELECT type 
                        FROM chats AS st 
                        WHERE (st.sender_id = ${object.user_id} AND st.receiver_id = users.id) 
                        OR (st.sender_id = users.id AND st.receiver_id = ${object.user_id}) 
                        ORDER BY created_at DESC LIMIT 1
                    ) AS type,
                    (
                        SELECT created_at 
                        FROM chats AS st 
                        WHERE (st.sender_id = ${object.user_id} AND st.receiver_id = users.id) 
                        OR (st.sender_id = users.id AND st.receiver_id = ${object.user_id}) 
                        ORDER BY created_at DESC LIMIT 1
                    ) AS created_at
                FROM chats
                LEFT JOIN users ON users.id = chats.receiver_id
                WHERE chats.sender_id = ${object.user_id} 
                AND chats.deleted_at IS NULL 
                AND chats.group_id IS NULL
            )
        ) AS p_pn 
        GROUP BY 
            user_id, 
            first_name, 
            last_name, 
            user_type, 
            profile_image, 
            online_status, 
            message, 
            created_at, 
            type, 
            unread_count 
        ORDER BY created_at DESC`, function (error, data) {
                connection.release();
                if (error) {
                    callback(false);
                } else {
                    callback(data);
                }
            });
        }
    });
};

var get_messages = function (object, callback) {
    // console.log("Send msf call bacj")
    con_mysql.getConnection(function (error, connection) {
        if (error) {
            console.log("CONNECTIOn ERROR ON SEND MESSAFE")
            callback(false);
        } else {
            connection.query(`UPDATE chats SET read_at = NOW() WHERE chats.sender_id = ${object.receiver_id} AND chats.receiver_id = ${object.sender_id} AND read_at IS NULL`, function (error, data) {
                if (error) {
                    console.log("FAILED TO VERIFY LIST")
                    callback(false);
                } else {
                    connection.query(`select 
                    users.id as user_id,
                    users.first_name,
                    users.last_name,
                    users.user_type,
                    users.profile_image, 
                    chats.id, 
                    chats.sender_id,
                    chats.receiver_id, 
                    chats.message,
                    chats.thumbnail,
                    chats.type,
                    chats.created_at
                    from chats 
                    inner join users          on chats.sender_id = users.id
                    WHERE 
                    (
                        (chats.sender_id = ${object.sender_id}   AND chats.receiver_id = ${object.receiver_id}) OR 
                        (chats.sender_id = ${object.receiver_id} AND chats.receiver_id = ${object.sender_id})
                    ) AND chats.deleted_at IS NULL
                    group by chats.id ORDER BY chats.id ASC;`, function (error, data) {
                        connection.release();
                        if (error) {
                            callback(false);
                        } else {
                            callback(data);
                        }
                    });
                }
            });
        }
    });
};

// SEND MESSAGE FUNCTION
var send_message = function (object, callback) {
    // console.log("Send msf call bacj")
    con_mysql.getConnection(function (error, connection) {
        if (error) {
            console.log("CONNECTIOn ERROR ON SEND MESSAFE")
            callback(false);
        } else {
            var new_message = mysql_real_escape_string(object.message);

            if (object.parent_id != undefined) {
                var parent_id = object.parent_id
            } else {
                var parent_id = 0;
            }

            if (object.thumbnail != undefined) {
                var thumbnail = object.thumbnail
            } else {
                var thumbnail = '';
            }

            connection.query(`INSERT INTO chats (sender_id, receiver_id, message, thumbnail, type, parent_id, created_at) VALUES ('${object.sender_id}', '${object.receiver_id}', '${new_message}', '${thumbnail}', '${object.chat_type}', '${parent_id}', NOW())`, function (error, data) {
                if (error) {
                    console.log("FAILED TO VERIFY LIST")
                    callback(false);
                } else {
                    console.log("update_list has been successfully executed...");
                    connection.query(`select 
                            users.id as user_id,
                            users.first_name,
                            users.last_name,
                            users.user_type,
                            users.profile_image, 
                            (select device_token from users where id = '${object.receiver_id}') as device_token,
                            chats.id, 
                            chats.sender_id,
                            chats.receiver_id, 
                            chats.message,
                            chats.thumbnail,
                            chats.type,
                            chats.created_at        
                            from chats 
                            inner join users          on chats.sender_id = users.id
                            WHERE  (chats.id = ${data.insertId})`, function (error, data) {
                        connection.release();
                        if (error) {
                            callback(false);
                        } else {
                            callback(data);
                        }
                    });
                }
            });
        }
    });
};

// READ MESSAGE FUNCTION
var read_messages = async function (object) {
    return new Promise((resolve, reject) => {
        con_mysql.getConnection(function (error, connection) {
            if (error) {
                reject(error);
            } else {
                connection.query(`UPDATE chats SET read_at = NOW() WHERE chats.sender_id = ${object.receiver_id} AND chats.receiver_id = ${object.sender_id} AND read_at IS NULL`, function (error, data) {
                    connection.release();
                    if (error) {
                        console.log("read_messages query error...", error)
                        reject(error);
                    } else {
                        resolve(data);
                    }
                });
            }
        });
    });
}

function mysql_real_escape_string(str) {
    return str.replace(/[\0\x08\x09\x1a\n\r"'\\\%]/g, function (char) {
        switch (char) {
            case "\0":
                return "\\0";
            case "\x08":
                return "\\b";
            case "\x09":
                return "\\t";
            case "\x1a":
                return "\\z";
            case "\n":
                return "\\n";
            case "\r":
                return "\\r";
            case "\"":
            case "'":
            case "\\":
            case "%":
                return "\\" + char; // prepends a backslash to backslash, percent,
            // and double/single quotes
            default:
                return char;
        }
    });
}


// SERVER LISTENER
server.listen(3000, function () {
    console.log("Server is running on port 3000");
});
