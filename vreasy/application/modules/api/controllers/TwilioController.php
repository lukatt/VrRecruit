<?php


use Vreasy\Models\Base;
use Vreasy\Models\Task;
use Vreasy\Utils\Arrays;
use Vreasy\Presenters\Json\Task as TaskJson;

class Api_TwilioController extends Vreasy_Rest_Controller
{

    public function preDispatch()
    {
        parent::preDispatch();

        $this->req = $this->getRequest();

    }

    public function postAction()
    {


        $body = $this->req->getParam('body');
        $from = $this->req->getParam('from');

        $yesWords = array("yes","si","ok");
        $noWords = array("no","nein","no way");
        $doneWords = array("done", "completed","finito");

        //$AccountSid = "ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX";
        //$AuthToken = "YYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY";

        //$client = new Services_Twilio($AccountSid, $AuthToken);
        

        $this->tasks = Task::where( ['assigned_phone' => $from]);

        if(in_array($body, $yesWords) || in_array($body, $noWords)){
            //this is accpetance or denial
            foreach ($this->tasks as $value) {
                if($value->status == "pending"){
                    $this->task = $value;
                    $this->task->responded_at = (new DateTime())->format('Y-m-d H:i:s');
                    break;
                }
            }

            if(!$this->task){
                //there are no pending tasks for that provider -> send him that message

                //$client->account->messages->sendMessage( $from, "You don't have any pending tasks");
                
                $this->getResponse()->setHttpResponseCode(201);//using this code for testing should be 200, everything is ok on twilio side
                return $this->getResponse()->sendResponse();
            }
        } elseif(in_array($body, $doneWords)){
            //this is completion of a task
            foreach ($this->tasks as $value) {
                if($value->status == "accepted"){
                    $this->task = $value;
                    $this->task->finished_at = (new DateTime())->format('Y-m-d H:i:s');
                    break;
                }
            }

            if(!$this->task){
                //there are no accepted tasks for that provider -> send him that message

                //$client->account->messages->sendMessage( $from, "You don't have any accepted tasks");
                
                $this->getResponse()->setHttpResponseCode(202); //using this code for testing should be 200, everything is ok on twilio side
                return $this->getResponse()->sendResponse();
            }

        }

        if(in_array($body, $yesWords)){
            $this->task->status="accepted";
        }
        elseif (in_array($body, $noWords)) {
            $this->task->status="denied";
           }
        elseif (in_array($body, $doneWords)){
            $this->task->status="done";
        } 
        else{
            //provider did not do a good job at typing the message

            //$client->account->messages->sendMessage( $from, "We can't understand your message, please check for errors and try again"); 
            $this->getResponse()->setHttpResponseCode(203); //using this code for testing should be 200, everything is ok on twilio side
            return $this->getResponse()->sendResponse();       
        }
        $this->task->save();

        $this->getResponse()->setHttpResponseCode(200);
        return $this->getResponse()->sendResponse();

    }
}
