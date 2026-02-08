<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Business;
use App\Models\Role;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Plan;
use App\Models\MpesaPayment;
use App\Services\WhatsApp\SessionManager;
use App\Services\WhatsApp\WorkflowManager;
use App\Services\WhatsApp\MessageFormatter;
use App\Services\WhatsApp\PermissionChecker;
use App\Services\CmsService;
use App\Services\PaymentService;
use App\Notifications\BusinessRegistered;
use App\Events\GeneralNotification;

class WhatsAppController extends Controller
{
    private SessionManager $session;
    private WorkflowManager $workflow;
    private MessageFormatter $formatter;
    private PermissionChecker $permissions;
    private CmsService $cms;
    private PaymentService $payment;

    public function __construct()
    {
        $this->session = new SessionManager();
        $this->workflow = new WorkflowManager();
        $this->formatter = new MessageFormatter();
        $this->permissions = new PermissionChecker($this->session);
        $this->cms = new CmsService();
        $this->payment = new PaymentService();
    }

    /**
     * Handle incoming WhatsApp messages from Twilio.
     * Supports both GET (verification) and POST (messages).
     */
    public function webhook(Request $request)
    {
        // Handle GET request (Twilio webhook verification)
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => 'success',
                'message' => 'WhatsApp webhook is active and ready to receive messages.'
            ]);
        }
        
        // Handle POST request (actual WhatsApp messages)
        $message = $request->input('Body', '');
        $from = $request->input('From', '');

        // Extract phone number (remove 'whatsapp:' prefix)
        $phone = str_replace('whatsapp:', '', $from);

        Log::info('WhatsApp message received', [
            'from' => $phone,
            'message' => $message,
            'raw_request' => $request->all()
        ]);

        // Check for missing sender
        if (empty($from)) {
            $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response><Message>Error: Missing sender information.</Message></Response>';
            return response($twiml, 400)->header('Content-Type', 'text/xml');
        }

        // Route the message and get response
        $response = $this->routeMessage($message, $phone);

        // Return TwiML response for Twilio
        $twiml = '<?xml version="1.0" encoding="UTF-8"?>' .
                 '<Response>' .
                 '<Message>' . htmlspecialchars($response, ENT_XML1, 'UTF-8') . '</Message>' .
                 '</Response>';

        return response($twiml)->header('Content-Type', 'text/xml');
    }

    /**
     * Route incoming message based on state and context.
     */
    private function routeMessage(string $message, string $phone): string
    {
        // Global commands (work anywhere)
        $cmd = strtolower(trim($message));
        
        if (in_array($cmd, ['cancel', 'abort'])) {
            $this->workflow->clearWorkflow($phone);
            $this->session->clearContext($phone);
            return "Operation cancelled. " . ($this->session->hasSession($phone) ? "Type 'Menu' to continue." : "Type 'Hi' to start.");
        }

        if ($cmd === 'logout') {
            $this->session->destroySession($phone);
            $this->workflow->clearWorkflow($phone);
            return "Logged out successfully. Type 'Hi' to start again.";
        }

        if (in_array($cmd, ['back', 'menu']) && $this->session->hasSession($phone)) {
            $this->workflow->clearWorkflow($phone);
            return $this->showMainMenu($phone);
        }

        // Check if in a workflow
        if ($this->workflow->hasActiveWorkflow($phone)) {
            return $this->handleWorkflowStep($message, $phone);
        }

        // Check if authenticated
        if (!$this->session->hasSession($phone)) {
            return $this->handleUnauthenticated($message, $phone);
        }

        // Authenticated - route to main menu or command
        return $this->handleMainMenuSelection($message, $phone);
    }

    /**
     * Handle messages from unauthenticated users.
     */
    private function handleUnauthenticated(string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        if (in_array($cmd, ['hi', 'hello', 'start', 'help'])) {
            return "Welcome to *ModernPOS*! 👋\n\nYour business management assistant.\n\n1️⃣ Login\n2️⃣ Register\n\nType '1' or 'Login' to proceed.";
        }

        if (in_array($cmd, ['1', 'login'])) {
            $this->workflow->setState($phone, 'LOGIN_WAIT_EMAIL');
            return "Please enter your *Email Address*:";
        }

        if (in_array($cmd, ['2', 'register'])) {
            $this->workflow->setState($phone, 'REG_WAIT_NAME');
            return "Welcome to ModernPOS Registration! 🎉\n\nPlease enter your *Full Name*:";
        }

        return "Please type 'Hi' to start.";
    }

    /**
     * Handle workflow steps.
     */
    private function handleWorkflowStep(string $message, string $phone): string
    {
        $state = $this->workflow->getState($phone);
        
        // Authentication workflows
        if (str_starts_with($state, 'REG_')) {
            return $this->handleRegistrationFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'LOGIN_')) {
            return $this->handleLoginFlow($state, $message, $phone);
        }

        // Business workflows (require authentication)
        if (!$this->session->hasSession($phone)) {
            $this->workflow->clearWorkflow($phone);
            return "Session expired. Please login again.";
        }

        if (str_starts_with($state, 'SALES_')) {
            return $this->handleSalesFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'INV_')) {
            return $this->handleInventoryFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'CUST_')) {
            return $this->handleCustomerFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'STAFF_')) {
            return $this->handleStaffFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'REPORT_')) {
            return $this->handleReportFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'SUB_') || str_starts_with($state, 'VIEW_PLANS') || str_starts_with($state, 'COLLECT_PAYMENT_PHONE') || str_starts_with($state, 'PAYMENT_INITIATED')) {
            return $this->handleSubscriptionFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'ADMIN_SUB_') || str_starts_with($state, 'VIEW_ALL_SUBS') || str_starts_with($state, 'SEARCH_BUSINESS')) {
            return $this->handleSubscriptionManagementFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'SA_BUS_')) {
            return $this->handleSuperAdminBusinessFlow($state, $message, $phone);
        }

        if (str_starts_with($state, 'SA_ADM_')) {
            return $this->handleSuperAdminAdminFlow($state, $message, $phone);
        }

        return "Unknown workflow state. Type 'Menu' to restart.";
    }

    /**
     * Show main menu.
     */
    private function showMainMenu(string $phone): string
    {
        $user = $this->session->getUser($phone);
        $business = $this->session->getBusiness($phone);
        
        // Get role information
        $roleName = $this->permissions->getRoleDisplayName($phone);
        $roleBadge = $this->permissions->getRoleBadge($phone);
        
        // Get available menu options based on role
        $options = $this->permissions->getAvailableMenuOptions($phone);
        
        return $this->formatter->formatRoleBasedMenu(
            $user->name,
            $business?->name,
            $roleName,
            $roleBadge,
            $options
        );
    }

    /**
     * Handle main menu selection.
     */
    private function handleMainMenuSelection(string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));
        
        // Get available options for this user
        $options = $this->permissions->getAvailableMenuOptions($phone);
        
        // Check if user selected a number
        if (isset($options[$cmd])) {
            $action = $options[$cmd]['key'];
        } else {
            // Try to match by name
            $action = null;
            foreach ($options as $option) {
                if (strtolower($option['name']) === $cmd || str_contains(strtolower($option['name']), $cmd)) {
                    $action = $option['key'];
                    break;
                }
            }
        }
        
        // Handle logout and menu
        if ($cmd === '0' || $cmd === 'logout') {
            $this->session->destroySession($phone);
            return "Logged out successfully. Type 'Hi' to start again.";
        }
        
        if ($cmd === 'menu') {
            return $this->showMainMenu($phone);
        }
        
        if ($cmd === 'dashboard') {
            return $this->getDashboard($phone);
        }

        // Route to appropriate workflow based on action
        return match($action) {
            'sales' => $this->initiateSalesWorkflow($phone),
            'inventory' => $this->initiateInventoryWorkflow($phone),
            'customers' => $this->initiateCustomerWorkflow($phone),
            'staff' => $this->initiateStaffWorkflow($phone),
            'reports' => $this->initiateReportWorkflow($phone),
            'subscriptions' => $this->initiateSubscriptionWorkflow($phone),
            'settings' => $this->initiateSettingsWorkflow($phone),
            'help' => $this->formatter->formatHelp(),
            'switch' => $this->initiateSwitchBusiness($phone),
            'business_mgmt' => $this->initiateSuperAdminBusinessManagement($phone),
            'support' => $this->initiateSuperAdminSupport($phone),
            'system_reports' => $this->initiateSuperAdminReports($phone),
            'manage_admins' => $this->initiateSuperAdminManageAdmins($phone),
            default => "Unknown command. Type 'Menu' to see options."
        };
    }

    // ==================== REGISTRATION FLOW ====================
    
    private function handleRegistrationFlow(string $state, string $message, string $phone): string
    {
        switch ($state) {
            case 'REG_WAIT_NAME':
                $this->workflow->updateData($phone, 'name', $message);
                $this->workflow->transition($phone, 'REG_WAIT_EMAIL');
                return "Nice to meet you, *{$message}*! 👋\n\nPlease enter your *Email Address*:";

            case 'REG_WAIT_EMAIL':
                if (!filter_var($message, FILTER_VALIDATE_EMAIL)) {
                    return $this->formatter->formatError("Invalid email format. Please try again:");
                }
                if (User::where('email', $message)->exists()) {
                    $this->workflow->clearWorkflow($phone);
                    return "This email is already registered.\n\nType 'Login' to access your account.";
                }
                $this->workflow->updateData($phone, 'email', $message);
                $this->workflow->transition($phone, 'REG_WAIT_BUSINESS');
                return "Great! What is the name of your *Business*?";

            case 'REG_WAIT_BUSINESS':
                $this->workflow->updateData($phone, 'business', $message);
                $this->workflow->transition($phone, 'REG_WAIT_PASSWORD');
                return "Almost done! Choose a *Password* for your account:\n\n_(Minimum 8 characters)_";

            case 'REG_WAIT_PASSWORD':
                if (strlen($message) < 8) {
                    return $this->formatter->formatError("Password must be at least 8 characters. Please try again:");
                }
                
                $data = $this->workflow->getData($phone);
                $user = $this->registerUser($data['name'], $data['email'], $data['business'], $message);
                
                $this->workflow->clearWorkflow($phone);
                $this->session->createSession($phone, $user->id);
                
                return $this->formatter->formatSuccess("Registration Complete! 🎉\n\nYour business '*{$data['business']}*' is ready.\n\nType 'Menu' to get started.");

            default:
                $this->workflow->clearWorkflow($phone);
                return "Something went wrong. Type 'Register' to try again.";
        }
    }

    private function registerUser(string $name, string $email, string $businessName, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $businessName, $password) {
            $business = Business::create([
                'name' => $businessName,
                'email' => $email,
                'currency' => 'KES',
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'current_business_id' => $business->id,
                'is_active' => true,
                'role' => 'admin',
            ]);

            $adminRole = Role::firstOrCreate(['name' => 'admin'], [
                'display_name' => 'Administrator',
                'level' => 100,
            ]);
            
            $user->roles()->attach($adminRole->id, ['business_id' => $business->id]);
            
            // Send welcome email notification (same as web app)
            try {
                $user->notify(new BusinessRegistered($business));
                
                // Broadcast real-time notification
                broadcast(new GeneralNotification(
                    $user->id,
                    'Welcome to ModernPOS! 🎉',
                    "Your business '{$businessName}' has been successfully registered via WhatsApp.",
                    'business.registered',
                    ['business_id' => $business->id]
                ));
                
                Log::info('WhatsApp registration notification sent', [
                    'user_id' => $user->id,
                    'business_id' => $business->id,
                    'email' => $email
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send WhatsApp registration notification', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id
                ]);
            }
            
            return $user;
        });
    }

    // ==================== LOGIN FLOW ====================
    
    private function handleLoginFlow(string $state, string $message, string $phone): string
    {
        switch ($state) {
            case 'LOGIN_WAIT_EMAIL':
                $this->workflow->updateData($phone, 'email', $message);
                $this->workflow->transition($phone, 'LOGIN_WAIT_PASSWORD');
                return "Please enter your *Password*:";

            case 'LOGIN_WAIT_PASSWORD':
                $data = $this->workflow->getData($phone);
                $user = User::where('email', $data['email'])->first();
                
                if ($user && Hash::check($message, $user->password)) {
                    $this->workflow->clearWorkflow($phone);
                    $this->session->createSession($phone, $user->id);
                    
                    return $this->formatter->formatSuccess("Login Successful! ✅\n\nWelcome back, *{$user->name}*!\n\nType 'Menu' to continue.");
                }
                
                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatError("Invalid credentials.\n\nType 'Login' to try again.");

            default:
                $this->workflow->clearWorkflow($phone);
                return "Something went wrong. Type 'Login' to try again.";
        }
    }

    // ==================== SALES WORKFLOW ====================
    
    private function initiateSalesWorkflow(string $phone): string
    {
        if (!$this->permissions->canAccessSales($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Sales');
        }
        
        $options = ['New Sale', 'Recent Sales', 'Sales History (Search)', 'Process Refund'];
        $this->workflow->setState($phone, 'SALES_MENU');
        return $this->formatter->formatSubmenu('Sales Management', $options);
    }

    private function handleSalesFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        switch ($state) {
            case 'SALES_MENU':
                if (in_array($cmd, ['1', 'new sale', 'new'])) {
                    $this->session->setContext($phone, 'cart', []);
                    $this->workflow->transition($phone, 'SALES_ADD_ITEM');
                    return "🛒 *New Sale*\n\nEnter product name or SKU to add:\n\n_(Type 'Done' when finished adding items)_";
                }
                if (in_array($cmd, ['2', 'recent', 'recent sales'])) {
                    return $this->viewRecentSales($phone);
                }
                if (in_array($cmd, ['3', 'history', 'search'])) {
                    $this->workflow->setState($phone, 'SALES_HISTORY_SEARCH');
                    return "🔍 *Sales Search*\n\nEnter Receipt Number (e.g., SL-12345) to view details:";
                }
                if ($cmd === '0') {
                    $this->workflow->clearWorkflow($phone);
                    return $this->showMainMenu($phone);
                }
                return "Invalid option. Please select 1-3 or type '0' for menu.";

            case 'SALES_HISTORY_SEARCH':
                $business = $this->session->getBusiness($phone);
                $sale = Sale::where('business_id', $business->id)
                    ->where('sale_number', 'like', "%{$message}%")
                    ->first();

                if (!$sale) {
                    return "No sale found with number \"{$message}\". Try again or type '0' for menu:";
                }

                $this->workflow->clearWorkflow($phone);
                return $this->formatDetailedReceipt($sale, $business);

            case 'SALES_ADD_ITEM':
                if ($cmd === 'done') {
                    $cart = $this->session->getContext($phone, 'cart') ?? [];
                    if (empty($cart)) {
                        return $this->formatter->formatError("Cart is empty. Add at least one item.");
                    }
                    $this->workflow->transition($phone, 'SALES_SELECT_CUSTOMER');
                    return "Select customer:\n\n1️⃣ Walk-in Customer\n2️⃣ Search Existing\n3️⃣ Add New Customer\n\nType your choice:";
                }
                
                return $this->addItemToCart($message, $phone);

            case 'SALES_SELECT_CUSTOMER':
                return $this->selectCustomer($message, $phone);

            case 'SALES_PAYMENT_METHOD':
                return $this->selectPaymentMethod($message, $phone);

            case 'SALES_CONFIRM':
                return $this->confirmSale($message, $phone);

            default:
                $this->workflow->clearWorkflow($phone);
                return "Workflow error. Type 'Sales' to start again.";
        }
    }

    private function addItemToCart(string $search, string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $product = Product::where('business_id', $business->id)
            ->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            })
            ->first();

        if (!$product) {
            return $this->formatter->formatError("Product not found. Try again or type 'Done' to finish:");
        }

        $cart = $this->session->getContext($phone, 'cart') ?? [];
        $cart[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => 1
        ];
        $this->session->setContext($phone, 'cart', $cart);

        $total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));
        
        return "✅ Added: *{$product->name}* - {$business->currency} " . number_format($product->price, 2) . "\n\n" .
               "Cart Total: {$business->currency} " . number_format($total, 2) . "\n\n" .
               "Add another item or type 'Done' to proceed:";
    }

    private function selectCustomer(string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));
        
        if (in_array($cmd, ['1', 'walk-in', 'walkin'])) {
            $this->session->setContext($phone, 'customer_id', null);
            $this->workflow->transition($phone, 'SALES_PAYMENT_METHOD');
            return "Select payment method:\n\n1️⃣ Cash\n2️⃣ M-Pesa\n3️⃣ Card\n\nType your choice:";
        }

        // For now, default to walk-in
        $this->session->setContext($phone, 'customer_id', null);
        $this->workflow->transition($phone, 'SALES_PAYMENT_METHOD');
        return "Select payment method:\n\n1️⃣ Cash\n2️⃣ M-Pesa\n3️⃣ Card\n\nType your choice:";
    }

    private function selectPaymentMethod(string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));
        
        $methodMap = [
            '1' => 'cash', 'cash' => 'cash',
            '2' => 'mpesa', 'm-pesa' => 'mpesa',
            '3' => 'card', 'card' => 'card'
        ];

        $method = $methodMap[$cmd] ?? 'cash';
        $this->session->setContext($phone, 'payment_method', $method);
        $this->workflow->transition($phone, 'SALES_CONFIRM');

        $cart = $this->session->getContext($phone, 'cart');
        $business = $this->session->getBusiness($phone);
        $total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));

        $summary = "📋 *Sale Summary*\n\n";
        $summary .= "*Items:*\n";
        foreach ($cart as $item) {
            $summary .= "• {$item['name']} x{$item['quantity']} - {$business->currency} " . number_format($item['price'] * $item['quantity'], 2) . "\n";
        }
        $summary .= "\n*Total:* {$business->currency} " . number_format($total, 2) . "\n";
        $summary .= "*Payment:* " . ucfirst($method) . "\n\n";
        $summary .= "Type 'Confirm' to complete or 'Cancel' to abort.";

        return $summary;
    }

    private function confirmSale(string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));
        
        if ($cmd !== 'confirm' && $cmd !== 'yes') {
            $this->workflow->clearWorkflow($phone);
            $this->session->clearContext($phone, 'cart');
            return "Sale cancelled. Type 'Menu' to continue.";
        }

        $business = $this->session->getBusiness($phone);
        $user = $this->session->getUser($phone);
        $cart = $this->session->getContext($phone, 'cart');
        $customerId = $this->session->getContext($phone, 'customer_id');
        $paymentMethod = $this->session->getContext($phone, 'payment_method');

        $total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));

        $sale = Sale::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'customer_id' => $customerId,
            'sale_number' => $business->generateSaleNumber(),
            'subtotal' => $total,
            'tax' => 0,
            'total_amount' => $total,
            'payment_method' => $paymentMethod,
            'payment_status' => 'completed',
            'status' => 'completed'
        ]);

        foreach ($cart as $item) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'subtotal' => $item['price'] * $item['quantity'],
                'total' => $item['price'] * $item['quantity']
            ]);

            // Update stock
            $product = Product::find($item['product_id']);
            if ($product) {
                $product->decrement('stock_quantity', $item['quantity']);
            }
        }

        $this->workflow->clearWorkflow($phone);
        $this->session->clearContext($phone, 'cart');
        
        // Broadcast real-time notification to business users
        try {
            $businessUsers = $business->users;
            foreach ($businessUsers as $businessUser) {
                broadcast(new GeneralNotification(
                    $businessUser->id,
                    'New Sale Completed 💰',
                    "Sale #{$sale->sale_number} completed via WhatsApp by {$user->name}. Total: {$business->currency} " . number_format($total, 2),
                    'sale.created',
                    [
                        'sale_id' => $sale->id,
                        'sale_number' => $sale->sale_number,
                        'total' => $total,
                        'created_by' => $user->name,
                        'channel' => 'whatsapp'
                    ]
                ));
            }
            
            Log::info('WhatsApp sale notification sent', [
                'sale_id' => $sale->id,
                'business_id' => $business->id,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp sale notification', [
                'error' => $e->getMessage(),
                'sale_id' => $sale->id
            ]);
        }

        return $this->formatter->formatSuccess("Sale Completed! 🎉\n\nReceipt #: *{$sale->sale_number}*\nTotal: {$business->currency} " . number_format($total, 2) . "\n\nType 'Menu' to continue.");
    }
    private function viewRecentSales(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $sales = Sale::where('business_id', $business->id)
            ->latest()
            ->take(5)
            ->get();

        if ($sales->isEmpty()) {
            return "No sales found.\n\nType 'Menu' to continue.";
        }

        $list = "📊 *Recent Sales*\n\n";
        foreach ($sales as $sale) {
            $list .= "• {$sale->sale_number} - {$business->currency} " . number_format($sale->total_amount, 2) . " ({$sale->created_at->format('M d, H:i')})\n";
        }

        $this->workflow->clearWorkflow($phone);
        return $list . "\nType 'Menu' to continue.";
    }

    private function formatDetailedReceipt(Sale $sale, Business $business): string
    {
        $saleData = [
            'sale_number' => $sale->sale_number,
            'date' => $sale->created_at->format('Y-m-d H:i'),
            'customer' => $sale->customer ? $sale->customer->name : 'Walk-in',
            'items' => $sale->items->map(fn($item) => [
                'name' => $item->product_name ?? ($item->product->name ?? 'Unknown'),
                'quantity' => $item->quantity,
                'total' => $business->currency . ' ' . number_format($item->total_amount, 2)
            ])->toArray(),
            'subtotal' => $business->currency . ' ' . number_format($sale->total_amount, 2),
            'total' => $business->currency . ' ' . number_format($sale->total_amount, 2),
            'payment_method' => ucfirst($sale->payment_method ?? 'Cash')
        ];

        return $this->formatter->formatReceipt($saleData);
    }

    // ==================== INVENTORY WORKFLOW ====================
    
    private function initiateInventoryWorkflow(string $phone): string
    {
        if (!$this->permissions->canAccessInventory($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Inventory');
        }
        
        $options = ['Add Product', 'Update Stock', 'Edit Product', 'Check Stock', 'Low Stock Alert'];
        $this->workflow->setState($phone, 'INV_MENU');
        return $this->formatter->formatSubmenu('Inventory Management', $options);
    }

    private function handleInventoryFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        switch ($state) {
            case 'INV_MENU':
                if (in_array($cmd, ['1', 'add', 'add product'])) {
                    return $this->initiateAddProduct($phone);
                }
                if (in_array($cmd, ['2', 'update', 'update stock'])) {
                    return $this->initiateUpdateStock($phone);
                }
                if (in_array($cmd, ['3', 'edit', 'edit product'])) {
                    return $this->initiateEditProduct($phone);
                }
                if (in_array($cmd, ['4', 'check', 'check stock'])) {
                    return $this->checkStock($phone);
                }
                if (in_array($cmd, ['5', 'low stock', 'alert'])) {
                    return $this->checkLowStock($phone);
                }
                if ($cmd === '0') {
                    $this->workflow->clearWorkflow($phone);
                    return $this->showMainMenu($phone);
                }
                return "Unknown option. Type '0' for menu.";

            case 'INV_ADD_NAME':
                $this->workflow->setData($phone, 'new_product_name', $message);
                $this->workflow->setState($phone, 'INV_ADD_SKU');
                return $this->formatter->formatStep('Add Product', 2, 4, "What is the *SKU* or *Barcode* for \"{$message}\"?\n(Type 'Skip' to auto-generate)");

            case 'INV_ADD_SKU':
                $sku = (strtolower($message) === 'skip') ? 'PRD-' . strtoupper(uniqid()) : $message;
                $this->workflow->setData($phone, 'new_product_sku', $sku);
                $this->workflow->setState($phone, 'INV_ADD_PRICE');
                return $this->formatter->formatStep('Add Product', 3, 4, "What is the *Selling Price* for this product?");

            case 'INV_ADD_PRICE':
                $price = filter_var($message, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if (!is_numeric($price)) {
                    return $this->formatter->formatError("Invalid price. Please enter a number (e.g., 150.00)");
                }
                $this->workflow->setData($phone, 'new_product_price', $price);
                $this->workflow->setState($phone, 'INV_ADD_STOCK');
                return $this->formatter->formatStep('Add Product', 4, 4, "How many items do you have in *Stock*?");

            case 'INV_ADD_STOCK':
                $stock = filter_var($message, FILTER_SANITIZE_NUMBER_INT);
                if (!is_numeric($stock)) {
                    return $this->formatter->formatError("Invalid quantity. Please enter a whole number.");
                }
                $this->workflow->setData($phone, 'new_product_stock', $stock);
                $this->workflow->setState($phone, 'INV_ADD_CONFIRM');
                
                $data = $this->workflow->getData($phone);
                return $this->formatter->formatFormConfirmation('New Product', [
                    'name' => $data['new_product_name'],
                    'sku' => $data['new_product_sku'],
                    'price' => $data['new_product_price'],
                    'stock' => $data['new_product_stock']
                ]);

            case 'INV_ADD_CONFIRM':
                if (strtolower($message) === 'yes') {
                    return $this->saveNewProduct($phone);
                } elseif (strtolower($message) === 'no') {
                    $this->workflow->clearWorkflow($phone);
                    return $this->initiateAddProduct($phone);
                }
                return "Please type *'Yes'* to save or *'No'* to restart.";

            case 'INV_STOCK_SEARCH':
                $business = $this->session->getBusiness($phone);
                $products = Product::where('business_id', $business->id)
                    ->where(function($q) use ($message) {
                        $q->where('name', 'like', "%{$message}%")
                          ->orWhere('sku', 'like', "%{$message}%");
                    })->limit(5)->get();

                if ($products->isEmpty()) {
                    return "No products found matching \"{$message}\". Try another name or SKU:";
                }

                $this->workflow->setState($phone, 'INV_STOCK_PICK');
                $this->workflow->setData($phone, 'search_results', $products->pluck('id')->toArray());
                
                $options = $products->map(fn($p) => "{$p->name} (Stock: {$p->quantity})")->toArray();
                return $this->formatter->formatSubmenu("Select Product", $options);

            case 'INV_STOCK_PICK':
                $results = $this->workflow->getData($phone, 'search_results');
                $index = (int)$cmd - 1;
                if (!isset($results[$index])) {
                    return "Invalid selection. Please choose a number from the list.";
                }

                $productId = $results[$index];
                $product = Product::find($productId);
                $this->workflow->setData($phone, 'selected_product_id', $productId);
                $this->workflow->setState($phone, 'INV_STOCK_ADJUST');
                return "Selected: *{$product->name}*\nCurrent Stock: *{$product->quantity}*\n\nHow many items are you *adding*? (Use negative number to subtract):";

            case 'INV_STOCK_ADJUST':
                $adjustment = filter_var($message, FILTER_SANITIZE_NUMBER_INT);
                if (!is_numeric($adjustment)) {
                    return $this->formatter->formatError("Invalid amount. Please enter a number.");
                }
                
                $productId = $this->workflow->getData($phone, 'selected_product_id');
                $product = Product::find($productId);
                
                $newQuantity = $product->quantity + $adjustment;
                if ($newQuantity < 0) {
                    return $this->formatter->formatError("Stock cannot be negative. Current stock is {$product->quantity}.");
                }

                $product->quantity = $newQuantity;
                $product->save();

                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatSuccess("Stock for *{$product->name}* updated to *{$newQuantity}*.");

            case 'INV_EDIT_SEARCH':
                $business = $this->session->getBusiness($phone);
                $products = Product::where('business_id', $business->id)
                    ->where(function($q) use ($message) {
                        $q->where('name', 'like', "%{$message}%")
                          ->orWhere('sku', 'like', "%{$message}%");
                    })->limit(5)->get();

                if ($products->isEmpty()) {
                    return "No products found matching \"{$message}\". Try another name or SKU:";
                }

                $this->workflow->setState($phone, 'INV_EDIT_PICK');
                $this->workflow->setData($phone, 'search_results', $products->pluck('id')->toArray());
                
                $options = $products->map(fn($p) => "{$p->name} (Price: {$p->selling_price})")->toArray();
                return $this->formatter->formatSubmenu("Select Product to Edit", $options);

            case 'INV_EDIT_PICK':
                $results = $this->workflow->getData($phone, 'search_results');
                $index = (int)$cmd - 1;
                if (!isset($results[$index])) {
                    return "Invalid selection. Please choose a number from the list.";
                }

                $productId = $results[$index];
                $this->workflow->setData($phone, 'selected_product_id', $productId);
                $this->workflow->setState($phone, 'INV_EDIT_MENU');
                
                $options = ['Name', 'Selling Price', 'SKU/Barcode'];
                return $this->formatter->formatSubmenu("What would you like to edit?", $options);

            case 'INV_EDIT_MENU':
                $fieldMap = ['1' => 'name', '2' => 'selling_price', '3' => 'sku'];
                $fieldNameMap = ['1' => 'Name', '2' => 'Selling Price', '3' => 'SKU/Barcode'];
                
                if (!isset($fieldMap[$cmd])) {
                    return "Invalid selection. Please choose 1, 2, or 3.";
                }

                $this->workflow->setData($phone, 'edit_field', $fieldMap[$cmd]);
                $this->workflow->setData($phone, 'edit_field_label', $fieldNameMap[$cmd]);
                $this->workflow->setState($phone, 'INV_EDIT_VALUE');
                return "Enter the new *{$fieldNameMap[$cmd]}*:";

            case 'INV_EDIT_VALUE':
                $field = $this->workflow->getData($phone, 'edit_field');
                $label = $this->workflow->getData($phone, 'edit_field_label');
                
                $this->workflow->setData($phone, 'edit_value', $message);
                $this->workflow->setState($phone, 'INV_EDIT_CONFIRM');
                
                return $this->formatter->formatConfirmation("Change {$label} to \"{$message}\"?");

            case 'INV_EDIT_CONFIRM':
                if (strtolower($message) === 'yes') {
                    $productId = $this->workflow->getData($phone, 'selected_product_id');
                    $field = $this->workflow->getData($phone, 'edit_field');
                    $value = $this->workflow->getData($phone, 'edit_value');
                    
                    $product = Product::find($productId);
                    $product->$field = $value;
                    $product->save();

                    $this->workflow->clearWorkflow($phone);
                    return $this->formatter->formatSuccess("Product *{$product->name}* updated successfully.");
                } elseif (strtolower($message) === 'no') {
                    $this->workflow->clearWorkflow($phone);
                    return $this->initiateEditProduct($phone);
                }
                return "Please type *'Yes'* to confirm or *'No'* to cancel.";
            
            default:
                $this->workflow->clearWorkflow($phone);
                return "Type 'Menu' to continue.";
        }
    }

    private function initiateUpdateStock(string $phone): string
    {
        $this->workflow->setState($phone, 'INV_STOCK_SEARCH');
        return "Which product would you like to update? (Enter name or SKU to search):";
    }

    private function initiateEditProduct(string $phone): string
    {
        $this->workflow->setState($phone, 'INV_EDIT_SEARCH');
        return "Which product would you like to edit? (Enter name or SKU to search):";
    }

    private function initiateAddProduct(string $phone): string
    {
        $this->workflow->setState($phone, 'INV_ADD_NAME');
        return $this->formatter->formatStep('Add Product', 1, 4, "What is the *Name* of the new product?");
    }

    private function saveNewProduct(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $data = $this->workflow->getData($phone);

        \Log::info("WA SaveNewProduct Attempt", ['data' => $data]);

        try {
            $product = Product::create([
                'business_id' => $business->id,
                'name' => $data['new_product_name'],
                'sku' => $data['new_product_sku'],
                'selling_price' => $data['new_product_price'],
                'quantity' => $data['new_product_stock'],
                'track_inventory' => true,
                'is_active' => true
            ]);

            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatSuccess("Product \"{$product->name}\" added successfully!\n\nType 'Menu' for more options.");
        } catch (\Exception $e) {
            Log::error("WhatsApp Add Product Error: " . $e->getMessage());
            return $this->formatter->formatError("Failed to save product. Please try again.");
        }
    }

    private function checkStock(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $products = Product::where('business_id', $business->id)
            ->orderBy('name')
            ->take(10)
            ->get();

        if ($products->isEmpty()) {
            $this->workflow->clearWorkflow($phone);
            return "No products found.\n\nType 'Menu' to continue.";
        }

        $list = "📦 *Stock Levels*\n\n";
        foreach ($products as $product) {
            $list .= "• *{$product->name}*: {$product->stock_quantity} units\n";
        }
        $list .= "\nType 'Menu' to continue.";

        $this->workflow->clearWorkflow($phone);
        return $list;
    }

    private function checkLowStock(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $products = Product::where('business_id', $business->id)
            ->where('stock_quantity', '<', 10)
            ->orderBy('stock_quantity')
            ->get();

        if ($products->isEmpty()) {
            $this->workflow->clearWorkflow($phone);
            return "✅ All products are well stocked!\n\nType 'Menu' to continue.";
        }

        $list = "⚠️ *Low Stock Alert*\n\n";
        foreach ($products as $product) {
            $list .= "• *{$product->name}*: {$product->stock_quantity} units\n";
        }
        $list .= "\nType 'Menu' to continue.";

        $this->workflow->clearWorkflow($phone);
        return $list;
    }

    // ==================== CUSTOMER WORKFLOW ====================
    
    private function initiateCustomerWorkflow(string $phone): string
    {
        if (!$this->permissions->canAccessCustomers($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Customers');
        }
        
        $options = ['View All Customers', 'Add New Customer', 'Search Customer'];
        $this->workflow->setState($phone, 'CUST_MENU');
        return $this->formatter->formatSubmenu('Customer Management', $options);
    }

    private function handleCustomerFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        switch ($state) {
            case 'CUST_MENU':
                if (in_array($cmd, ['1', 'view', 'view all'])) {
                    return $this->viewCustomers($phone);
                }
                if ($cmd === '0') {
                    $this->workflow->clearWorkflow($phone);
                    return $this->showMainMenu($phone);
                }
                return "Feature coming soon. Type '0' for menu.";

            default:
                $this->workflow->clearWorkflow($phone);
                return "Type 'Menu' to continue.";
        }
    }

    private function viewCustomers(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $customers = Customer::where('business_id', $business->id)
            ->orderBy('name')
            ->take(10)
            ->get();

        if ($customers->isEmpty()) {
            $this->workflow->clearWorkflow($phone);
            return "No customers found.\n\nType 'Menu' to continue.";
        }

        $list = "👥 *Customers*\n\n";
        foreach ($customers as $customer) {
            $list .= "• *{$customer->name}*\n  {$customer->phone}\n";
        }
        $list .= "\nType 'Menu' to continue.";

        $this->workflow->clearWorkflow($phone);
        return $list;
    }

    // ==================== STAFF WORKFLOW ====================
    
    private function initiateStaffWorkflow(string $phone): string
    {
        if (!$this->permissions->canManageUsers($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Staff Management');
        }
        
        $options = ['List Staff', 'Add Staff Member', 'Edit/Manage Staff'];
        $this->workflow->setState($phone, 'STAFF_MENU');
        return $this->formatter->formatSubmenu('Staff Management', $options);
    }

    private function handleStaffFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        if ($cmd === '0' || $cmd === 'menu') {
            $this->workflow->clearWorkflow($phone);
            return $this->showMainMenu($phone);
        }

        switch ($state) {
            case 'STAFF_MENU':
                if (in_array($cmd, ['1', 'list', 'list staff'])) {
                    return $this->listStaff($phone);
                }
                if (in_array($cmd, ['2', 'add', 'add staff'])) {
                    return $this->initiateAddStaff($phone);
                }
                if (in_array($cmd, ['3', 'edit', 'manage'])) {
                    return $this->initiateManageStaff($phone);
                }
                return "Unknown option. Type '0' for menu.";

            case 'STAFF_ADD_NAME':
                $this->workflow->setData($phone, 'new_staff_name', $message);
                $this->workflow->setState($phone, 'STAFF_ADD_EMAIL');
                return $this->formatter->formatStep('Add Staff', 2, 4, "What is the *Email Address* for \"{$message}\"?");

            case 'STAFF_ADD_EMAIL':
                if (!filter_var($message, FILTER_VALIDATE_EMAIL)) {
                    return $this->formatter->formatError("Invalid email address. Please try again:");
                }
                if (User::where('email', $message)->exists()) {
                    return $this->formatter->formatError("A user with this email already exists. Try another:");
                }
                $this->workflow->setData($phone, 'new_staff_email', $message);
                $this->workflow->setState($phone, 'STAFF_ADD_PASSWORD');
                return $this->formatter->formatStep('Add Staff', 3, 4, "Set a *Temporary Password* (min 8 chars):");

            case 'STAFF_ADD_PASSWORD':
                if (strlen($message) < 8) {
                    return $this->formatter->formatError("Password is too short. Must be at least 8 characters:");
                }
                $this->workflow->setData($phone, 'new_staff_password', $message);
                $this->workflow->setState($phone, 'STAFF_ADD_ROLE');
                
                $roles = Role::where('level', '<', 100)->get();
                $this->workflow->setData($phone, 'available_roles', $roles->pluck('name')->toArray());
                return $this->formatter->formatSubmenu("Select Role for Staff", $roles->pluck('name')->toArray());

            case 'STAFF_ADD_ROLE':
                $availableRoles = $this->workflow->getData($phone, 'available_roles');
                $index = (int)$cmd - 1;
                if (!isset($availableRoles[$index])) {
                    return "Invalid selection. Please choose a number from the list.";
                }

                $roleName = $availableRoles[$index];
                $this->workflow->setData($phone, 'new_staff_role', $roleName);
                $this->workflow->setState($phone, 'STAFF_ADD_CONFIRM');
                
                $data = $this->workflow->getData($phone);
                return $this->formatter->formatFormConfirmation('New Staff Member', [
                    'name' => $data['new_staff_name'],
                    'email' => $data['new_staff_email'],
                    'role' => $data['new_staff_role']
                ]);

            case 'STAFF_ADD_CONFIRM':
                if (strtolower($message) === 'yes') {
                    return $this->saveNewStaff($phone);
                } elseif (strtolower($message) === 'no') {
                    return $this->initiateAddStaff($phone);
                }
                return "Please type *'Yes'* to save or *'No'* to restart.";

            case 'STAFF_MANAGE_SEARCH':
                $business = $this->session->getBusiness($phone);
                $staff = User::whereHas('roles', function($q) use ($business) {
                        $q->wherePivot('business_id', $business->id);
                    })
                    ->where('name', 'like', "%{$message}%")
                    ->limit(5)->get();

                if ($staff->isEmpty()) {
                    return "No staff members found matching \"{$message}\". Try another name:";
                }

                $this->workflow->setState($phone, 'STAFF_MANAGE_PICK');
                $this->workflow->setData($phone, 'search_results', $staff->pluck('id')->toArray());
                
                $options = $staff->map(fn($u) => "{$u->name} (" . ($u->roles()->wherePivot('business_id', $business->id)->first()->name ?? 'N/A') . ")")->toArray();
                return $this->formatter->formatSubmenu("Select Staff Member", $options);

            case 'STAFF_MANAGE_PICK':
                $results = $this->workflow->getData($phone, 'search_results');
                $index = (int)$cmd - 1;
                if (!isset($results[$index])) {
                    return "Invalid selection. Please choose a number from the list.";
                }

                $staffId = $results[$index];
                $staffMember = User::find($staffId);
                $this->workflow->setData($phone, 'target_staff_id', $staffId);
                $this->workflow->setState($phone, 'STAFF_MANAGE_ACTION');
                
                $status = $staffMember->is_active ? 'Active' : 'Inactive';
                $options = [$staffMember->is_active ? 'Deactivate' : 'Activate', 'Change Role'];
                return $this->formatter->formatSubmenu("Managing: *{$staffMember->name}* ({$status})", $options);

            case 'STAFF_MANAGE_ACTION':
                $staffId = $this->workflow->getData($phone, 'target_staff_id');
                $staffMember = User::find($staffId);
                
                if ($cmd === '1') { // Toggle Active
                    $staffMember->is_active = !$staffMember->is_active;
                    $staffMember->save();
                    $this->workflow->clearWorkflow($phone);
                    $status = $staffMember->is_active ? 'activated' : 'deactivated';
                    return $this->formatter->formatSuccess("Staff member *{$staffMember->name}* has been {$status}.");
                }
                
                if ($cmd === '2') { // Change Role
                    $this->workflow->setState($phone, 'STAFF_MANAGE_ROLE');
                    $roles = Role::where('level', '<', 100)->get();
                    $this->workflow->setData($phone, 'available_roles', $roles->pluck('name')->toArray());
                    return $this->formatter->formatSubmenu("Select New Role", $roles->pluck('name')->toArray());
                }
                
                return "Unknown option. Type '0' for menu.";

            case 'STAFF_MANAGE_ROLE':
                $availableRoles = $this->workflow->getData($phone, 'available_roles');
                $index = (int)$cmd - 1;
                if (!isset($availableRoles[$index])) {
                    return "Invalid selection.";
                }

                $roleName = $availableRoles[$index];
                $staffId = $this->workflow->getData($phone, 'target_staff_id');
                $staffMember = User::find($staffId);
                $business = $this->session->getBusiness($phone);
                
                $staffMember->assignRole($roleName, $business->id);
                
                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatSuccess("Role for *{$staffMember->name}* updated to *{$roleName}*.");
        }

        return "Type '0' to return to main menu.";
    }

    private function listStaff(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $staff = User::whereHas('roles', function($q) use ($business) {
            $q->wherePivot('business_id', $business->id);
        })->get();

        $this->workflow->clearWorkflow($phone);
        return $this->formatter->formatStaffList([
            'business_name' => $business->name,
            'staff' => $staff->map(fn($u) => [
                'name' => $u->name,
                'role' => $u->roles()->wherePivot('business_id', $business->id)->first()->name ?? 'N/A',
                'is_active' => $u->is_active
            ])->toArray()
        ]);
    }

    private function initiateAddStaff(string $phone): string
    {
        $this->workflow->setState($phone, 'STAFF_ADD_NAME');
        return $this->formatter->formatStep('Add Staff', 1, 4, "What is the *Full Name* of the new staff member?");
    }

    private function saveNewStaff(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $data = $this->workflow->getData($phone);

        try {
            DB::beginTransaction();
            $user = User::create([
                'name' => $data['new_staff_name'],
                'email' => $data['new_staff_email'],
                'password' => Hash::make($data['new_staff_password']),
                'current_business_id' => $business->id,
                'is_active' => true
            ]);

            $user->assignRole($data['new_staff_role'], $business->id);
            DB::commit();

            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatSuccess("Staff member *{$user->name}* added successfully!\n\nEmail: {$user->email}\nRole: {$data['new_staff_role']}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("WhatsApp Add Staff Error: " . $e->getMessage());
            return $this->formatter->formatError("Failed to save staff member. Please try again.");
        }
    }

    private function initiateManageStaff(string $phone): string
    {
        $this->workflow->setState($phone, 'STAFF_MANAGE_SEARCH');
        return "Search for a staff member (Enter name):";
    }

    // ==================== REPORT WORKFLOW ====================
    
    private function initiateReportWorkflow(string $phone): string
    {
        if (!$this->permissions->canAccessReports($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Reports');
        }
        
        $options = ['Sales Report', 'Inventory Report', 'Customer Report'];
        $this->workflow->setState($phone, 'REPORT_MENU');
        return $this->formatter->formatSubmenu('Reports', $options);
    }

    private function handleReportFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        switch ($state) {
            case 'REPORT_MENU':
                if (in_array($cmd, ['1', 'sales', 'sales report'])) {
                    return $this->generateSalesReport($phone);
                }
                if (in_array($cmd, ['2', 'inventory', 'inventory report'])) {
                    return $this->generateInventoryReport($phone);
                }
                if (in_array($cmd, ['3', 'customer', 'customer report'])) {
                    return $this->generateCustomerReport($phone);
                }
                if ($cmd === '0') {
                    $this->workflow->clearWorkflow($phone);
                    return $this->showMainMenu($phone);
                }
                return "Unknown option. Type '0' for menu.";

            default:
                $this->workflow->clearWorkflow($phone);
                return "Type 'Menu' to continue.";
        }
    }

    private function generateSalesReport(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        
        $todaySales = Sale::where('business_id', $business->id)
            ->whereDate('created_at', today())
            ->sum('total_amount');
        
        $monthSales = Sale::where('business_id', $business->id)
            ->whereMonth('created_at', now()->month)
            ->sum('total_amount');

        $todayCount = Sale::where('business_id', $business->id)
            ->whereDate('created_at', today())
            ->count();

        $monthCount = Sale::where('business_id', $business->id)->whereMonth('created_at', now()->month)->count();

        $this->workflow->clearWorkflow($phone);

        return $this->formatter->formatSalesReport([
            'business_name' => $business->name,
            'today_sales' => $business->currency . ' ' . number_format($todaySales, 2),
            'today_count' => $todayCount,
            'month_sales' => $business->currency . ' ' . number_format($monthSales, 2),
            'month_count' => $monthCount
        ]);
    }

    private function generateInventoryReport(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        
        $totalProducts = Product::where('business_id', $business->id)->count();
        $totalItems = Product::where('business_id', $business->id)->sum('quantity');
        $lowStock = Product::where('business_id', $business->id)
            ->where('track_inventory', true)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->get();
            
        $this->workflow->clearWorkflow($phone);
        
        return $this->formatter->formatInventoryReport([
            'business_name' => $business->name,
            'total_products' => $totalProducts,
            'total_items' => (int)$totalItems,
            'low_stock_count' => $lowStock->count(),
            'low_stock_items' => $lowStock->take(5)->map(fn($p) => "{$p->name} ({$p->quantity})")->toArray()
        ]);
    }

    private function generateCustomerReport(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        
        $totalCustomers = Customer::where('business_id', $business->id)->count();
        $topSpenders = Sale::where('business_id', $business->id)
            ->whereNotNull('customer_id')
            ->select('customer_id', DB::raw('SUM(total_amount) as total_spent'))
            ->groupBy('customer_id')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->with('customer')
            ->get();
            
        $this->workflow->clearWorkflow($phone);
        
        return $this->formatter->formatCustomerReport([
            'business_name' => $business->name,
            'total_customers' => $totalCustomers,
            'top_spenders' => $topSpenders->map(fn($s) => ($s->customer->name ?? 'Unknown') . ": " . $business->currency . " " . number_format($s->total_spent, 2))->toArray()
        ]);
    }

    // ==================== SUBSCRIPTION WORKFLOW ====================
    
    private function initiateSubscriptionWorkflow(string $phone): string
    {
        if (!$this->permissions->canAccessSubscriptions($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Subscriptions');
        }

        // Check if SuperAdmin - they have different menu
        if ($this->session->isSuperAdmin($phone)) {
            return $this->initiateSubscriptionManagement($phone);
        }

        $business = $this->session->getBusiness($phone);
        $activeSub = $business->activeSubscription()->first();

        // Show subscription status
        $status = $this->formatter->formatSubscriptionStatus($activeSub, $business->currency);
        
        $status .= "\n\n📋 *Subscription Menu*\n\n";
        $status .= "1️⃣ View Payment History\n";
        $status .= "2️⃣ View Available Plans\n";
        
        if ($this->permissions->canManageSubscriptions($phone)) {
            $status .= "3️⃣ Upgrade/Change Plan\n";
        }
        
        $status .= "0️⃣ Back to Menu\n\n";
        $status .= "_Type your choice_";

        $this->workflow->setState($phone, 'SUB_MENU');
        return $status;
    }

    private function handleSubscriptionFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        if ($cmd === '0' || $cmd === 'cancel') {
            $this->workflow->clearWorkflow($phone);
            return $this->showMainMenu($phone);
        }

        switch ($state) {
            case 'SUB_MENU':
                if (in_array($cmd, ['1', 'history', 'payment history'])) {
                    return $this->showPaymentHistory($phone);
                }
                if (in_array($cmd, ['2', 'plans', 'view plans'])) {
                    return $this->showAvailablePlans($phone, true); // Keep workflow active for viewing details
                }
                if (in_array($cmd, ['3', 'upgrade', 'change plan']) && $this->permissions->canManageSubscriptions($phone)) {
                    return $this->showAvailablePlans($phone, true);
                }
                return $this->formatter->formatError("Invalid option. Type '0' to return to menu.");

            case 'VIEW_PLANS':
                if (is_numeric($cmd)) {
                    return $this->showPlanDetails($phone, (int)$cmd);
                }
                return $this->formatter->formatError("Please enter a plan number or '0' to cancel.");

            case 'COLLECT_PAYMENT_PHONE':
                return $this->handlePaymentPhoneCollection($phone, $message);

            case 'PAYMENT_INITIATED':
                if (in_array($cmd, ['status', 'check'])) {
                    return $this->checkPaymentStatus($phone);
                }
                return "💳 Payment pending...\n\nType 'Status' to check payment status or '0' for menu.";

            default:
                $this->workflow->clearWorkflow($phone);
                return "Type 'Menu' to continue.";
        }
    }

    /**
     * Show payment history.
     */
    private function showPaymentHistory(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $payments = SubscriptionPayment::where('business_id', $business->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $this->workflow->clearWorkflow($phone);
        return $this->formatter->formatSubscriptionHistory($payments, $business->currency);
    }

    /**
     * Show available plans.
     */
    private function showAvailablePlans(string $phone, bool $forUpgrade = false): string
    {
        $plans = Plan::where('is_active', true)->get();

        if ($plans->isEmpty()) {
            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatError("No plans available at the moment.");
        }

        $business = $this->session->getBusiness($phone);
        $message = $this->formatter->formatPlanList($plans, $business->currency);

        if ($forUpgrade) {
            $this->workflow->setState($phone, 'VIEW_PLANS');
        } else {
            $this->workflow->clearWorkflow($phone);
        }

        return $message;
    }

    /**
     * Show plan details.
     */
    private function showPlanDetails(string $phone, int $planIndex): string
    {
        $plans = Plan::where('is_active', true)->get();
        
        if (!isset($plans[$planIndex - 1])) {
            return $this->formatter->formatError("Invalid plan number. Please try again.");
        }

        $plan = $plans[$planIndex - 1];
        $business = $this->session->getBusiness($phone);

        $details = $this->formatter->formatPlanDetails($plan, $business->currency);
        
        // Only allow subscription if user has permission
        if ($this->permissions->canManageSubscriptions($phone)) {
            // Store selected plan
            $this->workflow->setData($phone, 'selected_plan_id', $plan->id);
            $this->workflow->setData($phone, 'billing_cycle', 'monthly'); // Default
            
            // Set state to collect phone number immediately
            $this->workflow->transition($phone, 'COLLECT_PAYMENT_PHONE');
            
            $details .= "\n\n📱 *Enter M-Pesa Phone Number*\n";
            $details .= "To upgrade to the *" . $plan->name . "* plan (Monthly), please enter the phone number to receive the M-Pesa prompt:\n\n";
            $details .= "Format: 0712345678 or 254712345678\n\n";
            
            if ($plan->price_yearly) {
                $details .= "_Note: To choose Yearly instead, type 'Yearly' before entering phone_";
            } else {
                $details .= "_Type '0' to cancel_";
            }
        } else {
            // Just viewing - clear workflow after showing details
            $this->workflow->clearWorkflow($phone);
            $details .= "\n\n_Type '0' to return to menu_";
        }

        return $details;
    }

    /**
     * Initiate M-Pesa payment for plan.
     */
    private function initiatePlanPayment(string $phone): string
    {
        $planId = $this->workflow->getData($phone, 'selected_plan_id');
        $billingCycle = $this->workflow->getData($phone, 'billing_cycle') ?? 'monthly';
        $paymentPhone = $this->workflow->getData($phone, 'payment_phone');

        if (!$planId) {
            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatError("Plan selection expired. Please start again.");
        }

        // If payment phone not collected yet, ask for it
        if (!$paymentPhone) {
            $this->workflow->setState($phone, 'COLLECT_PAYMENT_PHONE');
            return "📱 *Enter M-Pesa Phone Number*\n\n" .
                   "Please enter the phone number to receive the M-Pesa prompt:\n\n" .
                   "Format: 0712345678 or 254712345678\n\n" .
                   "_Type '0' to cancel_";
        }

        $plan = Plan::find($planId);
        if (!$plan) {
            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatError("Plan not found.");
        }

        $business = $this->session->getBusiness($phone);
        $user = $this->session->getUser($phone);

        // Determine amount based on billing cycle
        $amount = $billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        try {
            // Initiate M-Pesa STK Push using Platform Credentials
            $platformMpesa = $this->cms->getMpesaConfig();
            
            if (!$platformMpesa) {
                return $this->formatter->formatError("M-Pesa is not configured on the system. Please contact support.");
            }

            // Create or get pending subscription
            $subscription = Subscription::where('business_id', $business->id)
                ->where('plan_id', $plan->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if (!$subscription) {
                $subscription = Subscription::create([
                    'business_id' => $business->id,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'amount' => $amount,
                    'currency' => $business->currency ?? 'KES',
                    'status' => 'pending',
                    'payment_method' => 'MPESA_STK',
                    'payment_details' => [
                        'billing_cycle' => $billingCycle,
                        'phone_number' => $paymentPhone,
                    ],
                    'is_active' => false,
                ]);
            }

            $stkData = [
                'phone_number' => $paymentPhone,
                'amount' => $amount,
                'account_reference' => "SUB-{$subscription->id}",
                'transaction_desc' => "Subscription: {$plan->name}",
                'mpesa' => $platformMpesa,
            ];

            $result = $this->payment->initiateMpesaStkPush($stkData);

            if (!$result['success']) {
                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatError("Payment initiation failed: " . ($result['message'] ?? 'Unknown error'));
            }

            // Update subscription with checkout request ID
            $subscription->update([
                'checkout_request_id' => $result['checkout_request_id'] ?? null,
                'payment_details' => array_merge(
                    $subscription->payment_details ?? [],
                    [
                        'checkout_request_id' => $result['checkout_request_id'] ?? null,
                        'merchant_request_id' => $result['merchant_request_id'] ?? null,
                    ]
                ),
            ]);

            // Create MpesaPayment record for tracking (matches PublicSubscriptionController)
            MpesaPayment::create([
                'business_id' => $business->id,
                'subscription_id' => $subscription->id,
                'checkout_request_id' => $result['checkout_request_id'] ?? null,
                'merchant_request_id' => $result['merchant_request_id'] ?? null,
                'phone' => $paymentPhone,
                'amount' => $amount,
                'status' => 'pending',
                'metadata' => [
                    'type' => 'subscription',
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'billing_cycle' => $billingCycle,
                    'user_id' => $user->id,
                    'initiated_at' => now()->toDateTimeString(),
                ],
            ]);

            // Store checkout request ID for status checking
            $this->workflow->setData($phone, 'checkout_request_id', $result['checkout_request_id']);
            $this->workflow->transition($phone, 'PAYMENT_INITIATED');

            return $this->formatter->formatPaymentInitiation($paymentPhone, $amount, $business->currency) .
                   "\n\n_Type 'Status' to check payment status_";

        } catch (\Exception $e) {
            Log::error('WhatsApp subscription payment error', [
                'error' => $e->getMessage(),
                'phone' => $phone,
                'plan_id' => $planId
            ]);

            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatError("Payment initiation failed: " . $e->getMessage());
        }
    }

    /**
     * Check payment status.
     */
    private function checkPaymentStatus(string $phone): string
    {
        $checkoutRequestId = $this->workflow->getData($phone, 'checkout_request_id');

        if (!$checkoutRequestId) {
            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatError("No pending payment found.");
        }

        $payment = \App\Models\MpesaPayment::where('checkout_request_id', $checkoutRequestId)->first();

        if (!$payment) {
            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatError("Payment record not found.");
        }

        // Check if payment is successful
        if ($payment->status === 'success' || (string)$payment->result_code === '0') {
            $this->workflow->clearWorkflow($phone);
            
            $message = $this->formatter->formatPaymentStatus('success', $payment->receipt);
            
            // Check if subscription was created
            if ($payment->subscription_id) {
                $subscription = Subscription::find($payment->subscription_id);
                if ($subscription) {
                    $message .= "\n\n📋 *Subscription Activated*\n";
                    $message .= "Plan: {$subscription->plan_name}\n";
                    $message .= "Expires: " . $subscription->ends_at->format('M d, Y') . "\n\n";
                    $message .= "Type 'Menu' to continue.";
                }
            }
            
            return $message;
        }

        // Check if payment failed
        if ($payment->status === 'failed') {
            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatPaymentStatus('failed');
        }

        // Still pending
        return $this->formatter->formatPaymentStatus('pending') .
               "\n\n_Type 'Status' to check again or '0' for menu_";
    }

    /**
     * Handle phone number collection for payment.
     */
    private function handlePaymentPhoneCollection(string $phone, string $message): string
    {
        $cmd = strtolower(trim($message));

        // Handle billing cycle switches
        if ($cmd === 'yearly' || $cmd === '2') {
            $this->workflow->setData($phone, 'billing_cycle', 'yearly');
            return "✅ Billing cycle changed to *Yearly*.\n\n" .
                   "Please enter the M-Pesa phone number to receive the prompt:";
        }

        if ($cmd === 'monthly' || $cmd === '1') {
            $this->workflow->setData($phone, 'billing_cycle', 'monthly');
            return "✅ Billing cycle changed to *Monthly*.\n\n" .
                   "Please enter the M-Pesa phone number to receive the prompt:";
        }

        // Validate phone number format
        $cleanPhone = preg_replace('/[^0-9]/', '', $message);
        
        if (strlen($cleanPhone) < 9 || strlen($cleanPhone) > 12) {
            return $this->formatter->formatError("Invalid phone number. Please enter a valid Kenyan phone number (e.g., 0712345678).");
        }

        // Normalize to 254 format
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '254' . substr($cleanPhone, 1);
        } elseif (str_starts_with($cleanPhone, '7') || str_starts_with($cleanPhone, '1')) {
            $cleanPhone = '254' . $cleanPhone;
        }

        // Store phone and proceed to payment
        $this->workflow->setData($phone, 'payment_phone', $cleanPhone);
        
        return $this->initiatePlanPayment($phone);
    }

    // ==================== SETTINGS WORKFLOW ====================
    
    private function initiateSettingsWorkflow(string $phone): string
    {
        if (!$this->permissions->canAccessSettings($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Settings');
        }

        $options = ['View Profiles', 'Update Business Profile', 'Change My Name/Email', 'Change My Password'];
        $this->workflow->setState($phone, 'SETTINGS_MENU');
        return $this->formatter->formatSubmenu('Settings & Profile', $options);
    }

    private function handleSettingsFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        if ($cmd === '0' || $cmd === 'menu') {
            $this->workflow->clearWorkflow($phone);
            return $this->showMainMenu($phone);
        }

        switch ($state) {
            case 'SETTINGS_MENU':
                if (in_array($cmd, ['1', 'view'])) return $this->viewProfiles($phone);
                if (in_array($cmd, ['2', 'business'])) return $this->initiateUpdateBusiness($phone);
                if (in_array($cmd, ['3', 'me', 'name', 'email'])) return $this->initiateUpdateMe($phone);
                if (in_array($cmd, ['4', 'password'])) return $this->initiateChangePassword($phone);
                return "Unknown option. Type '0' for menu.";

            case 'SETTINGS_BUS_NAME':
                $business = $this->session->getBusiness($phone);
                $oldName = $business->name;
                $business->name = $message;
                $business->save();
                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatSuccess("Business name updated from \"{$oldName}\" to \"{$message}\".");

            case 'SETTINGS_BUS_ADDR':
                $business = $this->session->getBusiness($phone);
                $business->address = $message;
                $business->save();
                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatSuccess("Business address updated successfully.");

            case 'SETTINGS_ME_NAME':
                $user = $this->session->getUser($phone);
                $user->name = $message;
                $user->save();
                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatSuccess("Your name has been updated to \"{$message}\".");

            case 'SETTINGS_ME_EMAIL':
                if (!filter_var($message, FILTER_VALIDATE_EMAIL)) return $this->formatter->formatError("Invalid email. Try again:");
                $user = $this->session->getUser($phone);
                $user->email = $message;
                $user->save();
                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatSuccess("Your email has been updated to \"{$message}\".");

            case 'SETTINGS_PASSWORD':
                if (strlen($message) < 8) return $this->formatter->formatError("Password too short (min 8 chars). Try again:");
                $user = $this->session->getUser($phone);
                $user->password = Hash::make($message);
                $user->save();
                $this->workflow->clearWorkflow($phone);
                return $this->formatter->formatSuccess("Your password has been changed successfully. 🔐");

            case 'SETTINGS_BUS_INIT':
                if ($cmd === '1') {
                    $this->workflow->setState($phone, 'SETTINGS_BUS_NAME');
                    return "Enter the new *Business Name*:";
                }
                if ($cmd === '2') {
                    $this->workflow->setState($phone, 'SETTINGS_BUS_ADDR');
                    return "Enter the new *Business Address*:";
                }
                return "Invalid selection. Type '0' for menu.";

            case 'SETTINGS_ME_INIT':
                if ($cmd === '1') {
                    $this->workflow->setState($phone, 'SETTINGS_ME_NAME');
                    return "Enter your new *Name*:";
                }
                if ($cmd === '2') {
                    $this->workflow->setState($phone, 'SETTINGS_ME_EMAIL');
                    return "Enter your new *Email*:";
                }
                return "Invalid selection. Type '0' for menu.";
        }

        return "Type '0' to return to main menu.";
    }

    private function viewProfiles(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $user = $this->session->getUser($phone);
        $plan = $business->plan;

        $text = "🏢 *Business Profile*\n";
        $text .= "Name: {$business->name}\n";
        $text .= "Phone: {$business->phone}\n";
        $text .= "Address: {$business->address}\n";
        $text .= "Plan: " . ($plan ? $plan->name : 'No Plan') . "\n\n";

        $text .= "👤 *My Account*\n";
        $text .= "Name: {$user->name}\n";
        $text .= "Email: {$user->email}\n";
        $text .= "Role: " . ($user->roles()->wherePivot('business_id', $business->id)->first()->name ?? 'N/A') . "\n";

        $this->workflow->clearWorkflow($phone);
        return $text . "\nType 'Menu' for options.";
    }

    private function initiateUpdateBusiness(string $phone): string
    {
        $options = ['Update Name', 'Update Address'];
        $this->workflow->setState($phone, 'SETTINGS_BUS_INIT');
        return $this->formatter->formatSubmenu('Update Business Profile', $options);
    }

    private function initiateUpdateMe(string $phone): string
    {
        $options = ['Update My Name', 'Update My Email'];
        $this->workflow->setState($phone, 'SETTINGS_ME_INIT');
        return $this->formatter->formatSubmenu('Update My Account', $options);
    }

    private function initiateChangePassword(string $phone): string
    {
        $this->workflow->setState($phone, 'SETTINGS_PASSWORD');
        return "🔐 *Change Password*\n\nPlease enter your *New Password* (min 8 characters):";
    }

    // ==================== SUPERADMIN FEATURES ====================
    
    private function initiateSuperAdminBusinessManagement(string $phone): string
    {
        if (!$this->permissions->canAccessSuperAdminFeatures($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Business Management');
        }
        
        $this->workflow->setState($phone, 'SA_BUS_SEARCH');
        return "🔱 *SuperAdmin: Business Management*\n\nEnter Business Name or ID to search:";
    }

    private function handleSuperAdminBusinessFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));
        if ($cmd === '0' || $cmd === 'menu') {
            $this->workflow->clearWorkflow($phone);
            return $this->showMainMenu($phone);
        }

        switch ($state) {
            case 'SA_BUS_SEARCH':
                $businesses = Business::where('name', 'like', "%{$message}%")
                    ->orWhere('id', 'like', "%{$message}%")
                    ->limit(5)->get();

                if ($businesses->isEmpty()) {
                    return "No businesses found matching \"{$message}\". Try again:";
                }

                $this->workflow->setState($phone, 'SA_BUS_PICK');
                $this->workflow->setData($phone, 'search_results', $businesses->pluck('id')->toArray());
                
                $options = $businesses->map(fn($b) => "{$b->name} (#{$b->id}) [" . ($b->is_active ? 'Active' : 'Suspended') . "]")->toArray();
                return $this->formatter->formatSubmenu("Select Business", $options);

            case 'SA_BUS_PICK':
                $results = $this->workflow->getData($phone, 'search_results');
                $index = (int)$cmd - 1;
                if (!isset($results[$index])) return "Invalid selection.";

                $businessId = $results[$index];
                $this->workflow->setData($phone, 'target_business_id', $businessId);
                $this->workflow->setState($phone, 'SA_BUS_ACTION');
                
                $business = Business::find($businessId);
                $owner = $business->users()->wherePivot('business_id', $business->id)->first();
                $plan = $business->plan;
                
                $details = "🏢 *{$business->name}* (#{$business->id})\n";
                $details .= "Status: " . ($business->is_active ? '✅ Active' : '🚫 Suspended') . "\n";
                $details .= "Owner: " . ($owner ? $owner->name : 'N/A') . "\n";
                $details .= "Phone: {$business->phone}\n";
                $details .= "Plan: " . ($plan ? $plan->name : 'No Plan') . "\n\n";
                $details .= "What would you like to do?";
                
                $options = [$business->is_active ? 'Suspend Business' : 'Activate Business', 'View Subscriptions', 'View Ledger'];
                return $this->formatter->formatSubmenu($details, $options);

            case 'SA_BUS_ACTION':
                $businessId = $this->workflow->getData($phone, 'target_business_id');
                $business = Business::find($businessId);
                
                if ($cmd === '1') { // Toggle Status
                    $business->is_active = !$business->is_active;
                    $business->save();
                    $this->workflow->clearWorkflow($phone);
                    return $this->formatter->formatSuccess("Business *{$business->name}* has been " . ($business->is_active ? 'activated' : 'suspended') . ".");
                }
                
                if ($cmd === '2') { // View Subs
                    $this->workflow->setState($phone, 'SEARCH_BUSINESS'); // Reuse existing sub mgmt flow
                    return $this->handleSubscriptionManagementFlow('SEARCH_BUSINESS', $business->name, $phone);
                }

                return "Invalid option. Type '0' for menu.";
        }

        return "Type '0' for menu.";
    }
    
    private function initiateSuperAdminSupport(string $phone): string
    {
        if (!$this->permissions->canAccessSuperAdminFeatures($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Support Tickets');
        }
        
        return "🔱 *SuperAdmin: Support Tickets*\n\n" .
               "Community support is handled via the dashboard.\n\n" .
               "Type 'Menu' to return.";
    }
    
    private function initiateSuperAdminReports(string $phone): string
    {
        if (!$this->permissions->canAccessSuperAdminFeatures($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'System Reports');
        }

        $totalBusinesses = Business::count();
        $activeBusinesses = Business::where('is_active', true)->count();
        $suspendedBusinesses = Business::where('is_active', false)->count();

        $totalPlatformSales = Sale::sum('total_amount');
        $subscriptionRevenue = MpesaPayment::whereNotNull('subscription_id')
            ->where('status', MpesaPayment::STATUS_SUCCESS)
            ->sum('amount');

        $report = "🔱 *SuperAdmin: System Overview*\n\n";
        $report .= "🏢 *Businesses*\n";
        $report .= "Total: {$totalBusinesses}\n";
        $report .= "Active: ✅ {$activeBusinesses}\n";
        $report .= "Suspended: 🚫 {$suspendedBusinesses}\n\n";

        $report .= "💰 *Financials*\n";
        $report .= "Subscription Revenue: KES " . number_format($subscriptionRevenue, 2) . "\n";
        $report .= "Platform Sales Total: KES " . number_format($totalPlatformSales, 2) . "\n\n";

        $report .= "Type 'Menu' to return.";
        
        $this->workflow->clearWorkflow($phone);
        return $report;
    }

    private function initiateSuperAdminManageAdmins(string $phone): string
    {
        if (!$this->permissions->canAccessSuperAdminFeatures($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Admin Management');
        }

        $this->workflow->setState($phone, 'SA_ADM_NAME');
        return "🔱 *SuperAdmin: Add Admin*\n\nEnter the *Full Name* of the new SuperAdmin:";
    }

    private function handleSuperAdminAdminFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));
        if ($cmd === '0' || $cmd === 'menu') {
            $this->workflow->clearWorkflow($phone);
            return $this->showMainMenu($phone);
        }

        switch ($state) {
            case 'SA_ADM_NAME':
                $this->workflow->setData($phone, 'new_admin_name', $message);
                $this->workflow->setState($phone, 'SA_ADM_EMAIL');
                return "Step 2/3: Enter the *Email Address* for \"{$message}\":";

            case 'SA_ADM_EMAIL':
                if (!filter_var($message, FILTER_VALIDATE_EMAIL)) return "Invalid email. Try again:";
                if (User::where('email', $message)->exists()) return "User already exists. Try another email:";
                
                $this->workflow->setData($phone, 'new_admin_email', $message);
                $this->workflow->setState($phone, 'SA_ADM_PASS');
                return "Step 3/3: Set a *Temporary Password* (min 8 characters):";

            case 'SA_ADM_PASS':
                if (strlen($message) < 8) return "Password too short. Try again:";
                
                $data = $this->workflow->getData($phone);
                try {
                    DB::beginTransaction();
                    $user = User::create([
                        'name' => $data['new_admin_name'],
                        'email' => $data['new_admin_email'],
                        'password' => Hash::make($message),
                        'is_active' => true
                    ]);

                    $saRole = Role::where('name', 'SuperAdmin')->orWhere('level', '>=', 100)->first();
                    if ($saRole) {
                        $user->roles()->attach($saRole->id);
                    }

                    DB::commit();
                    $this->workflow->clearWorkflow($phone);
                    return $this->formatter->formatSuccess("SuperAdmin account *{$user->name}* created successfully.");
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("SA Admin Creation Error: " . $e->getMessage());
                    return $this->formatter->formatError("Failed to create admin. Try again.");
                }
        }

        return "Type '0' for menu.";
    }

    // ==================== HELPER METHODS ====================
    
    private function getDashboard(string $phone): string
    {
        $business = $this->session->getBusiness($phone);
        $user = $this->session->getUser($phone);

        // Today's stats
        $todaySales = Sale::where('business_id', $business->id)
            ->whereDate('created_at', today())
            ->sum('total_amount');
        $todayOrders = Sale::where('business_id', $business->id)
            ->whereDate('created_at', today())
            ->count();

        // Yesterday's stats
        $yesterdaySales = Sale::where('business_id', $business->id)
            ->whereDate('created_at', \Carbon\Carbon::yesterday())
            ->sum('total_amount');

        // Monthly stats
        $thisMonthSales = Sale::where('business_id', $business->id)
            ->whereMonth('created_at', now()->month)
            ->sum('total_amount');
        
        $lastMonthSales = Sale::where('business_id', $business->id)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('total_amount');

        $growth = $lastMonthSales > 0 
            ? (($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100 
            : 0;

        // Alerts
        $lowStockCount = Product::where('business_id', $business->id)
            ->where('track_inventory', true)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->count();

        $this->workflow->clearWorkflow($phone);

        return $this->formatter->formatDashboard([
            'business_name' => $business->name,
            'today_sales' => $business->currency . ' ' . number_format($todaySales, 2),
            'today_orders' => $todayOrders,
            'yesterday_sales' => $business->currency . ' ' . number_format($yesterdaySales, 2),
            'month_sales' => $business->currency . ' ' . number_format($thisMonthSales, 2),
            'growth' => round($growth, 1),
            'low_stock' => $lowStockCount
        ]);
    }

    private function initiateSwitchBusiness(string $phone): string
    {
        $businesses = $this->session->getUserBusinesses($phone);
        
        if (count($businesses) <= 1) {
            return "You only have one business.\n\nType 'Menu' to continue.";
        }

        $list = "🏢 *Your Businesses*\n\n";
        foreach ($businesses as $index => $business) {
            $number = $index + 1;
            $list .= "{$number}️⃣ {$business['name']}\n";
        }
        $list .= "\nType the number to switch:";

        $this->workflow->setState($phone, 'SWITCH_BUSINESS');
        return $list;
    }

    // ==================== SUPERADMIN SUBSCRIPTION MANAGEMENT ====================
    
    /**
     * Initiate SuperAdmin subscription management.
     */
    private function initiateSubscriptionManagement(string $phone): string
    {
        if (!$this->permissions->canViewAllSubscriptions($phone)) {
            return $this->permissions->getPermissionDeniedMessage($phone, 'Subscription Management');
        }

        $menu = "🔱 *Subscription Management*\n\n";
        $menu .= "1️⃣ View Metrics\n";
        $menu .= "2️⃣ View All Subscriptions\n";
        $menu .= "3️⃣ Search Business\n";
        $menu .= "0️⃣ Back to Menu\n\n";
        $menu .= "_Type your choice_";

        $this->workflow->setState($phone, 'ADMIN_SUB_MENU');
        return $menu;
    }

    /**
     * Handle SuperAdmin subscription management flow.
     */
    private function handleSubscriptionManagementFlow(string $state, string $message, string $phone): string
    {
        $cmd = strtolower(trim($message));

        if ($cmd === '0' || $cmd === 'cancel') {
            $this->workflow->clearWorkflow($phone);
            return $this->showMainMenu($phone);
        }

        switch ($state) {
            case 'ADMIN_SUB_MENU':
                if (in_array($cmd, ['1', 'metrics'])) {
                    return $this->showSubscriptionMetrics($phone);
                }
                if (in_array($cmd, ['2', 'all', 'view all'])) {
                    return $this->showAllSubscriptions($phone, 1);
                }
                if (in_array($cmd, ['3', 'search'])) {
                    $this->workflow->setState($phone, 'SEARCH_BUSINESS');
                    return "🔍 *Search Business*\n\nEnter business name to search:\n\n_Type '0' to cancel_";
                }
                return $this->formatter->formatError("Invalid option. Type '0' to return to menu.");

            case 'VIEW_ALL_SUBS':
                if ($cmd === 'next') {
                    $page = $this->workflow->getData($phone, 'page') ?? 1;
                    return $this->showAllSubscriptions($phone, $page + 1);
                }
                if ($cmd === 'previous' || $cmd === 'prev') {
                    $page = $this->workflow->getData($phone, 'page') ?? 1;
                    return $this->showAllSubscriptions($phone, max(1, $page - 1));
                }
                return $this->formatter->formatError("Type 'Next', 'Previous', or '0' for menu.");

            case 'SEARCH_BUSINESS':
                if (strlen($cmd) < 2) {
                    return $this->formatter->formatError("Please enter at least 2 characters.");
                }
                return $this->searchBusinessSubscriptions($phone, $message);

            default:
                $this->workflow->clearWorkflow($phone);
                return "Type 'Menu' to continue.";
        }
    }

    /**
     * Show subscription metrics for SuperAdmin.
     */
    private function showSubscriptionMetrics(string $phone): string
    {
        $metrics = [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'pending' => Subscription::where('status', 'pending')->count(),
            'expired' => Subscription::where('status', 'expired')->count(),
            'suspended' => Subscription::where('status', 'suspended')->count(),
            'revenue' => [
                'month' => SubscriptionPayment::whereMonth('created_at', now()->month)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'total' => SubscriptionPayment::where('status', 'completed')->sum('amount'),
            ],
            'recent' => [
                'today' => Subscription::whereDate('created_at', today())->count(),
                'week' => Subscription::where('created_at', '>=', now()->subWeek())->count(),
            ],
        ];

        $this->workflow->clearWorkflow($phone);
        return $this->formatter->formatSubscriptionMetrics($metrics);
    }

    /**
     * Show all subscriptions with pagination.
     */
    private function showAllSubscriptions(string $phone, int $page = 1): string
    {
        $perPage = 10;
        $subscriptions = Subscription::with('business')
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $total = Subscription::count();

        $this->workflow->setState($phone, 'VIEW_ALL_SUBS');
        $this->workflow->setData($phone, 'page', $page);

        return $this->formatter->formatSubscriptionList($subscriptions, $page, $total);
    }

    /**
     * Search business subscriptions.
     */
    private function searchBusinessSubscriptions(string $phone, string $query): string
    {
        $businesses = Business::where('name', 'like', "%{$query}%")
            ->with(['subscriptions' => function($q) {
                $q->orderBy('created_at', 'desc')->limit(5);
            }])
            ->limit(5)
            ->get();

        if ($businesses->isEmpty()) {
            $this->workflow->clearWorkflow($phone);
            return $this->formatter->formatError("No businesses found matching '{$query}'.");
        }

        $result = "🔍 *Search Results*\n\n";
        $result .= "Found " . $businesses->count() . " business(es):\n\n";

        foreach ($businesses as $index => $business) {
            $number = $index + 1;
            $result .= "{$number}. *{$business->name}*\n";
            
            $activeSub = $business->activeSubscription()->first();
            if ($activeSub) {
                $result .= "   ✅ {$activeSub->plan_name}\n";
                $result .= "   Expires: " . $activeSub->ends_at->format('M d, Y') . "\n";
            } else {
                $result .= "   ❌ No active subscription\n";
            }
            
            $result .= "\n";
        }

        $result .= "_Type '0' to return to menu_";

        $this->workflow->clearWorkflow($phone);
        return $result;
    }
}
