<?php

require 'include/ticket.php';
require 'include/functions.php';

function exec_ogp_module()
{
    global $db, $view;

    if (isset($_SESSION['ticket'])) {
        unset($_SESSION['ticket']);
    }

    $ticket = new Ticket($db);
    $isAdmin = $db->isAdmin($_SESSION['user_id']);

    echo '<h2>'.get_lang('viewing_ticket').'</h2>';

    $tid = (int)$_GET['tid'];
    $uid = $_GET['uid'];
    $ticketData = $ticket->getTicket($tid, $uid);

    if (!$ticket->exists($tid, $uid)) {
        print_failure(get_lang('ticket_not_found'));
        $view->refresh("?m=tickets");

        return;
    }

    if (!$isAdmin && !$ticket->authorized($_SESSION['user_id'], $tid, $uid)) {
        print_failure(get_lang('ticket_cant_read'));
        $view->refresh("?m=tickets");

        return;
    }

    if (!$ticketData) {
        print_failure(get_lang('cant_view_ticket'));
        $view->refresh("?m=tickets");

        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['ticket_close'])) {
            $ticket->updateStatus($tid, $uid, 0);
            $view->refresh("?m=tickets&p=viewticket&tid=".$tid."&uid=".$uid, 0);
            return;
        }

        if (isset($_POST['ticket_submit_response'])) {
            $_POST = array_map('trim', $_POST);
            $_SESSION['ticketReply'] = $_POST['reply_content'];

            $errors = array();

            if (empty($_POST['reply_content'])) {
                $errors[] = get_lang('no_ticket_reply');
            } elseif (strlen($_POST['reply_content']) < 4) {
                $errors[] = get_lang('invalid_ticket_reply_length');
            }

            if (empty($errors)) {
                $reply = $ticket->reply($tid, $_SESSION['user_id'], getClientIPAddress(), strip_real_escape_string($_POST['reply_content']), $isAdmin, $uid);
                
                if (!$reply) {
                    echo ticketErrors(array(get_lang('failed_to_reply')));
                    $view->refresh("?m=tickets&p=submitticket", 60);
                    return;
                }

                if (isset($_SESSION['ticketReply'])) {
                    unset($_SESSION['ticketReply']);
                }

                $view->refresh("?m=tickets&p=viewticket&tid=".$tid."&uid=".$uid, 0);

                return;
            } else {
                echo ticketErrors($errors);
                $view->refresh("?m=tickets&p=viewticket&tid=".$tid."&uid=".$uid, 60);
                return;
            }
        }
    }

    echo ticketHeader($ticketData);

    if ($ticketData['status'] == 0) {
        echo '<div class="ticket_closed">'.get_lang('ticket_is_closed').'</div>';

        echo '<div class="ticket_reply_notice">';
        echo '<div class="left">'.get_lang('reply').'</div>';
        echo '<div class="right">+</div>';
        echo '<div class="clear"></div>';
        echo '</div>';
    }

    echo '<div class="ticket_ReplyBox status_'.ticketCodeToName($ticketData['status'], true).'">
        <form method="POST">
            <textarea name="reply_content" style="width:100%;" rows="12">'.(isset($_SESSION['ticketReply']) ? $_SESSION['ticketReply'] : '').'</textarea>
            <input type="submit" class="ticket_button" name="ticket_submit_response" value="'. get_lang('ticket_submit_response') . '">
        '.($ticketData['status'] != 0 ? '<input type="submit" class="ticket_button" name="ticket_close" value="'. get_lang('ticket_close') . '">' : '').'
        </form>
    </div>';

    if (!empty($ticketData['replies'])) {
        echo '<div class="replyContainer">';
        foreach ($ticketData['replies'] as $replyData) {
            echo ticketReply($replyData, $uid, $isAdmin, false);
        }
        echo '</div>';
    } else {
        echo '<div class="no_ticket_replies">'.get_lang('no_ticket_replies').'</div>';
    }

    echo ticketReply($ticketData, $uid, $isAdmin, true); ?>

<script>
    $(function() {
        $(".ticket_reply_notice").click(function() {
            var state = ($(".right").text() == "+" ? "-" : "+");
            $(".ticket_ReplyBox").slideToggle(function() {
                $(".right").text(state);
            });
        });

        $("input[name=star]").click(function() {
            var data = {
                reply_id: this.getAttribute('id').split(/[ ,]+/)[0].replace(/\D/g, ''),
                tid: this.getAttribute('data-tid'),
                uid: this.getAttribute('data-uid'),
                rating: this.getAttribute('value')
            };

            $.ajax({
                type: "POST",
                url: "home.php?m=tickets&p=rate&type=cleared&data_type=json",
                data: data,
                dataType: "json"
            });
        });
    });
</script>

<?php
}