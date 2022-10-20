<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Illuminate\View\View;

class Livelookup extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'livelookup
    {source_id=""} 
    {customer_id=" "}
    {first_name=" "}
    {last_name=" "}
    {email=" "}
    {phone=" "}
    {request=" "}
    {sUserID=" "}
    {xStatus=" "}
    {xCategory=" "}
    {xPersonAssignedTo=" "}
    {other?*}
';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $connection = $this->connect();
        echo $this->users($this->argument('email'));
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illsluminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }

    private function connect()
    {
        $guzzle = new \GuzzleHttp\Client();
        $url = 'https://login.microsoftonline.com/' . config('app.MS_TENANT_ID') . '/oauth2/token?api-version=' . config('app.MS_GRAPH_API_VERSION');

        $response  = json_decode($guzzle->post($url, [
            'form_params' => [
                'client_id' => config('app.MS_CLIENT_ID'),
                'client_secret' => config('app.MS_CLIENT_SECRET'),
                'resource' => 'https://graph.microsoft.com/',
                'grant_type' => 'client_credentials',
            ],
        ])->getBody()->getContents());

        $graph = new Graph();

        return $graph->setBaseUrl("https://graph.microsoft.com")
            ->setApiVersion(config('app.MS_GRAPH_API_VERSION'))
            ->setAccessToken($response->access_token);
    }

    private function users($email)
    {

        $graph = self::connect();

        $query = '/users?$select=surName,givenName,mail,mobilephone,jobtitle&$expand=manager&$filter=mail eq \''.$email.'\'';

        $results =  $graph->createRequest("get", $query)
            ->addHeaders(array("Content-Type" => "application/json"))
            ->setReturnType(Model\User::class)
            ->setTimeout("1000")
            ->execute();
        
        $xml =  view("livelookup",['results' => $results]);
        return $xml;
    }
}
