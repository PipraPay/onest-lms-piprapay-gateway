<?php

namespace Modules\Payment\PaymentMethods\piprapay;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Event\Entities\EventRegistration;
use Modules\Order\Entities\Order;
use Modules\Subscription\Entities\PackagePurchase;

class method
{
    protected $currency;
    protected $order_id;
    protected $package_id;
    protected $event_id;
    protected $pp_base_url;
    protected $pp_api_key;
    protected $pp_currency;
    
    public function __construct()
    {
        $this->currency = "BDT"; //currency();
        $this->order_id = 'piprapay.payments.order_id';
        $this->package_id = 'stripe.payments.package_id';
        $this->event_id = 'stripe.payments.event_id';
        $this->pp_base_url = env('PP_BASE_URL');
        $this->pp_api_key = env('PP_API_KEY');
        $this->pp_currency = env('PP_CURRENCY');
    }

    public function process(Order $order)
    {
        $user = $order->user;

        $url = $this->pp_base_url.'/create-charge';
        
        $data = [
            'full_name' => $user->name,
            'email_mobile' => @$user->email,
            'amount' => $order->total_amount,
            'metadata' => [
                'value_c' => $order->user_id,
                'value_b' => $order->id,
                'value_a' => substr(md5($order->id), 0, 10)
            ],
            'redirect_url' => url("/payments/verify/piprapay?status=success"),
            'return_type' => 'GET',
            'cancel_url' => url("/payments/verify/piprapay?status=fail"),
            'webhook_url' => url("/payments/verify/piprapay?status=success"),
            'currency' => $this->pp_currency
        ];
        
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'mh-piprapay-api-key: '.$this->pp_api_key
        ];
        
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $responses = curl_exec($ch);
        
        curl_close($ch);
        
        $response = json_decode($responses, true);
        
        if (isset($response['pp_url'])) {
            session()->put($this->order_id, $order->id);
            
            return $response['pp_url'];
        }
        return redirect()->route('checkout.index')->with('danger', ___('alert.'.$responses));
        
    }

    public function verify(Request $request)
    {
        // Get raw POST data
        $rawData = file_get_contents("php://input");
    
        if (empty($rawData)) {
            echo "<script>location.href='https://" . $_SERVER['HTTP_HOST'] . "/student/courses';</script>";
            exit;
        }
    
        $data = json_decode($rawData, true);
    
        // Get API Key from headers
        $headers = getallheaders();
        $received_api_key = '';
    
        if (isset($headers['mh-piprapay-api-key'])) {
            $received_api_key = $headers['mh-piprapay-api-key'];
        } elseif (isset($headers['Mh-Piprapay-Api-Key'])) {
            $received_api_key = $headers['Mh-Piprapay-Api-Key'];
        } elseif (isset($_SERVER['HTTP_MH_PIPRAPAY_API_KEY'])) {
            $received_api_key = $_SERVER['HTTP_MH_PIPRAPAY_API_KEY'];
        }
    
        // Validate API Key
        $your_api_key = $this->pp_api_key;
    
        if ($received_api_key !== $your_api_key) {
            return response()->json(["status" => false, "message" => "Unauthorized request."], 401);
        }
    
        // Prepare request to PipraPay API
        $ppId = $data['pp_id'] ?? null;
    
        if (!$ppId) {
            return response()->json(['status' => false, 'message' => 'pp_id is missing.'], 422);
        }
    
        $jsonData = json_encode(['pp_id' => $ppId]);
    
        $ch = curl_init($this->pp_base_url . '/verify-payments');
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'content-type: application/json',
            'mh-piprapay-api-key: ' . $your_api_key
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    
        $result = curl_exec($ch);
        curl_close($ch);
    
        $response = json_decode($result, true);
    
        // Validate response metadata
        if (
            !isset($response['metadata']['value_b']) ||
            !isset($response['metadata']['value_c']) ||
            !isset($response['status'])
        ) {
            return response()->json(['status' => false, 'message' => 'Invalid response from PipraPay.'], 400);
        }
    
        session()->forget($this->order_id);
    
        $order = Order::where('id', $response['metadata']['value_b'])
            ->where('user_id', $response['metadata']['value_c'])
            ->with('user')
            ->first();
    
        if (!$order) {
            return response()->json(['status' => false, 'message' => 'Order not found.'], 404);
        }
    
        if ($response['status'] == "completed") {
            $order->update([
                'status' => 'paid',
            ]);
        } else {
            if ($response['status'] == "pending") {
                
            }else{
                $order->update([
                    'status' => 'failed',
                ]);
            }
        }
    
        return response()->json(['status' => true, 'message' => 'Order updated successfully.']);
    }
}
