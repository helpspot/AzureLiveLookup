<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Illuminate\Support\Facades\Log;

class LivelookupFuzzy extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'livelookupfuzzy
    {--source_id=} 
    {--customer_id=}
    {--first_name=}
    {--last_name=}
    {--email=}
    {--phone=}
    {--request=}
    {--sUserId=}
    {--xStatus=}
    {--xCategory=}
    {--xPersonAssignedTo=}
    {other?*}
';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'A fuzzy live lookup that returns partial matches';

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->ignoreValidationErrors();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $connection = $this->connect();
        echo $this->users($this->option('email'),$this->option('first_name'),$this->option('last_name'));

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

        try {
            $response  = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => config('app.MS_CLIENT_ID'),
                    'client_secret' => config('app.MS_CLIENT_SECRET'),
                    'resource' => 'https://graph.microsoft.com/',
                    'grant_type' => 'client_credentials',
                ],
            ])->getBody()->getContents());
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            echo \GuzzleHttp\Psr7\Message::toString($e->getRequest());
            echo \GuzzleHttp\Psr7\Message::toString($e->getResponse());
            die;
        }


        $graph = new Graph();

        return $graph->setBaseUrl("https://graph.microsoft.com")
            ->setApiVersion(config('app.MS_GRAPH_API_VERSION'))
            ->setAccessToken($response->access_token);
    }

    private function users($email,$first_name,$last_name)
    {

        $graph = self::connect();

        $query = '/users?$select=surName,givenName,mail,mobilephone,jobtitle,employeeId,businessPhones&$search="';

        if (!empty($email)) {
        $query .= 'mail:'.$email;
        } elseif (!empty($last_name) && !empty($first_name)) {
        $query .= 'givenName:'.$first_name.'" AND "surName:'.$last_name;
        } elseif (!empty($last_name)) {
        $query .= 'surName:'.$last_name;
        } elseif (!empty($first_name)) {
        $query .= 'givenName:'.$first_name;
        }
        $query .= '"';
        $results = $graph->createRequest("get", $query)
        ->addHeaders(array("ConsistencyLevel" => "eventual"))
        ->addHeaders(array("Content-Type" => "application/json"))
        ->setReturnType(Model\User::class)
        ->setTimeout("1000")
        ->execute();
        $xml =  view("livelookup",['results' => $results]);
        return $xml;
    }
}