<template>
    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Mini Wallet</h1>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Welcome, {{ user?.name }}</p>
                        <p class="text-xs text-gray-500">{{ user?.email }}</p>
                        <!-- Pusher Connection Status -->
                        <div class="mt-2 flex items-center gap-2">
                            <span class="text-xs font-medium">Pusher:</span>
                            <span 
                                class="text-xs px-2 py-1 rounded"
                                :class="{
                                    'bg-green-100 text-green-800': pusherConnectionStatus === 'subscribed',
                                    'bg-yellow-100 text-yellow-800': pusherConnectionStatus === 'connected' || pusherConnectionStatus === 'connecting',
                                    'bg-red-100 text-red-800': pusherConnectionStatus === 'error' || pusherConnectionStatus === 'disconnected',
                                    'bg-gray-100 text-gray-800': pusherConnectionStatus === 'not_configured' || pusherConnectionStatus === 'no_token'
                                }"
                            >
                                {{ pusherConnectionStatus === 'subscribed' ? '✅ Subscribed' : pusherConnectionStatus === 'connected' ? 'Connected (not subscribed)' : pusherConnectionStatus }}
                            </span>
                            <span v-if="pusherConnectionError" class="text-xs text-red-600" :title="pusherConnectionError">
                                ⚠️
                            </span>
                        </div>
                        <!-- Warning if not subscribed -->
                        <div v-if="pusherConnectionStatus !== 'subscribed' && pusherConnectionStatus !== 'not_configured' && pusherConnectionStatus !== 'no_token'" class="mt-1 text-xs text-yellow-600">
                            ⚠️ Not subscribed to channel - you won't receive real-time updates
                        </div>
                        <!-- Last event received indicator -->
                        <div v-if="lastEventReceived" class="mt-1 text-xs text-green-600">
                            ✅ Last event: {{ lastEventReceived.eventName }} at {{ new Date(lastEventReceived.timestamp).toLocaleTimeString() }}
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Current Balance</p>
                        <p class="text-2xl font-bold text-green-600">{{ formatCurrency(balance) }}</p>
                    </div>
                </div>
            </div>

            <!-- Transfer Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Send Money</h2>
                <form @submit.prevent="sendMoney" class="space-y-4">
                    <div class="relative">
                        <label for="receiver_search" class="block text-sm font-medium text-gray-700 mb-1">
                            Recipient
                        </label>
                        <div class="relative">
                            <input
                                id="receiver_search"
                                v-model="receiverSearch"
                                type="text"
                                @input="searchUsers"
                                @focus="showDropdown = true"
                                @blur="handleBlur"
                                autocomplete="off"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': errors.receiver_id }"
                                placeholder="Search by ID, name, or email"
                            />
                            <div v-if="searchingUsers" class="absolute right-3 top-2.5">
                                <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Dropdown Results -->
                        <div
                            v-if="showDropdown && (searchResults.length > 0 || receiverSearch.length >= 2)"
                            class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                        >
                            <div v-if="searchResults.length === 0 && receiverSearch.length >= 2 && !searchingUsers" class="p-3 text-sm text-gray-500 text-center">
                                No users found
                            </div>
                            <div
                                v-for="result in searchResults"
                                :key="result.id"
                                @mousedown="selectUser(result)"
                                class="p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                            >
                                <div class="font-medium text-gray-900">{{ result.name }}</div>
                                <div class="text-sm text-gray-500">ID: {{ result.id }} | {{ result.email }}</div>
                            </div>
                        </div>
                        
                        <!-- Selected User Display -->
                        <div v-if="selectedReceiver" class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded-md">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-medium text-gray-900">{{ selectedReceiver.name }}</span>
                                    <span class="text-sm text-gray-500 ml-2">(ID: {{ selectedReceiver.id }})</span>
                                </div>
                                <button
                                    type="button"
                                    @click="clearReceiver"
                                    class="text-red-600 hover:text-red-800 text-sm"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                        
                        <p v-if="errors.receiver_id" class="mt-1 text-sm text-red-600">{{ errors.receiver_id }}</p>
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">
                            Amount
                        </label>
                        <input
                            id="amount"
                            v-model.number="transferForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': errors.amount }"
                            placeholder="0.00"
                        />
                        <p v-if="errors.amount" class="mt-1 text-sm text-red-600">{{ errors.amount }}</p>
                        <p v-if="transferForm.amount" class="mt-1 text-xs text-gray-500">
                            Commission (1.5%): {{ formatCurrency(transferForm.amount * 0.015) }} | 
                            Total: {{ formatCurrency(transferForm.amount * 1.015) }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="loading">Sending...</span>
                        <span v-else>Send Money</span>
                    </button>
                </form>

                <!-- Success/Error Messages -->
                <div v-if="message" class="mt-4 p-3 rounded-md" :class="messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                    {{ message }}
                </div>
            </div>

            <!-- Logout Button -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form id="logoutForm" method="POST" action="/logout">
                    <input type="hidden" name="_token" :value="getCsrfToken()">
                    <button
                        type="submit"
                        class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                    >
                        Logout
                    </button>
                </form>
            </div>

            <!-- Transaction History -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Transaction History</h2>
                <div v-if="loadingTransactions" class="text-center py-8">
                    <p class="text-gray-600">Loading transactions...</p>
                </div>
                <div v-else-if="transactions.length === 0" class="text-center py-8">
                    <p class="text-gray-600">No transactions yet</p>
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="transaction in transactions"
                        :key="transaction.id"
                        class="border border-gray-200 rounded-md p-4"
                        :class="transaction.sender_id === user?.id ? 'bg-red-50' : 'bg-green-50'"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-medium text-gray-900">
                                    {{ transaction.sender_id === user?.id ? 'Sent to' : 'Received from' }}
                                    {{ transaction.sender_id === user?.id ? transaction.receiver?.name : transaction.sender?.name }}
                                </p>
                                <p class="text-sm text-gray-600">{{ transaction.receiver?.email || transaction.sender?.email }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ formatDate(transaction.created_at) }}</p>
                            </div>
                            <div class="text-right">
                                <p
                                    class="text-lg font-bold"
                                    :class="transaction.sender_id === user?.id ? 'text-red-600' : 'text-green-600'"
                                >
                                    {{ transaction.sender_id === user?.id ? '-' : '+' }}{{ formatCurrency(transaction.amount) }}
                                </p>
                                <p v-if="transaction.sender_id === user?.id" class="text-xs text-gray-500">
                                    Fee: {{ formatCurrency(transaction.commission_fee) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Set up CSRF token
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// Get auth token from meta tag or localStorage
let authToken = document.querySelector('meta[name="auth-token"]')?.getAttribute('content') || localStorage.getItem('auth_token');
if (authToken && authToken !== 'null' && authToken !== '') {
    axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
    localStorage.setItem('auth_token', authToken);
} else {
    authToken = null;
    localStorage.removeItem('auth_token');
}

// Pusher configuration
window.Pusher = Pusher;

// Initialize Echo - will be set up after user loads
let echo = null;
const pusherConnectionStatus = ref('disconnected');
const pusherConnectionError = ref('');

const initializeEcho = () => {
    if (!authToken || authToken === 'null' || authToken === '') {
        console.warn('Cannot initialize Echo: No auth token');
        pusherConnectionStatus.value = 'no_token';
        return;
    }

    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
    const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';

    if (!pusherKey || pusherKey === 'your-pusher-key') {
        console.error('Pusher key not configured. Please set VITE_PUSHER_APP_KEY in your .env file.');
        pusherConnectionStatus.value = 'not_configured';
        pusherConnectionError.value = 'Pusher key not configured';
        return;
    }

    console.log('Initializing Pusher with:', {
        key: pusherKey ? `${pusherKey.substring(0, 10)}...` : 'missing',
        cluster: pusherCluster,
        authEndpoint: '/broadcasting/auth'
    });

    // Disconnect existing connection if any
    if (echo) {
        try {
            echo.disconnect();
        } catch (e) {
            console.error('Error disconnecting Echo:', e);
        }
    }

    try {
        pusherConnectionStatus.value = 'connecting';
        pusherConnectionError.value = '';

        echo = new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: pusherCluster,
            forceTLS: true,
            encrypted: true,
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    Authorization: `Bearer ${authToken}`,
                    Accept: 'application/json',
                },
            },
            enabledTransports: ['ws', 'wss'],
            // Don't set namespace - let Laravel Echo handle it automatically
            // namespace: 'App.Events',
        });

        // Monitor Pusher connection state
        const pusher = echo.connector.pusher;

        pusher.connection.bind('connected', () => {
            console.log('✅ Pusher connected successfully');
            pusherConnectionStatus.value = 'connected';
            pusherConnectionError.value = '';
        });

        pusher.connection.bind('disconnected', () => {
            console.warn('⚠️ Pusher disconnected');
            pusherConnectionStatus.value = 'disconnected';
        });

        pusher.connection.bind('error', (error) => {
            console.error('❌ Pusher connection error:', error);
            pusherConnectionStatus.value = 'error';
            pusherConnectionError.value = error?.error?.message || error?.message || 'Connection error';
        });

        pusher.connection.bind('state_change', (states) => {
            console.log('Pusher state change:', states.previous, '->', states.current);
            if (states.current === 'connected') {
                pusherConnectionStatus.value = 'connected';
            } else if (states.current === 'disconnected') {
                pusherConnectionStatus.value = 'disconnected';
            } else if (states.current === 'failed') {
                pusherConnectionStatus.value = 'error';
            }
        });

        console.log('Echo initialized successfully');
    } catch (error) {
        console.error('❌ Error initializing Pusher:', error);
        pusherConnectionStatus.value = 'error';
        pusherConnectionError.value = error.message || 'Initialization error';
    }
};

const user = ref(null);
const balance = ref(0);
const transactions = ref([]);
const loading = ref(false);
const loadingTransactions = ref(false);
const message = ref('');
const messageType = ref('');

const transferForm = ref({
    receiver_id: null,
    amount: null,
});

const errors = ref({
    receiver_id: '',
    amount: '',
});

// Recipient search
const receiverSearch = ref('');
const searchResults = ref([]);
const selectedReceiver = ref(null);
const showDropdown = ref(false);
const searchingUsers = ref(false);
let searchTimeout = null;

// Format currency
const formatCurrency = (value) => {
    if (!value && value !== 0) return '$0.00';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
};

// Format date
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Get CSRF token for template
const getCsrfToken = () => {
    return csrfToken || '';
};

// Search users
const searchUsers = async () => {
    // Clear selected receiver if search changes
    if (selectedReceiver.value && receiverSearch.value !== `${selectedReceiver.value.name} (${selectedReceiver.value.email})`) {
        selectedReceiver.value = null;
        transferForm.value.receiver_id = null;
    }

    // Clear timeout if user is still typing
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    // Don't search if query is too short
    if (receiverSearch.value.length < 2) {
        searchResults.value = [];
        return;
    }

    // Debounce search
    searchTimeout = setTimeout(async () => {
        searchingUsers.value = true;
        try {
            const response = await axios.get('/api/users/search', {
                params: { q: receiverSearch.value }
            });
            searchResults.value = response.data.data || [];
        } catch (error) {
            console.error('Error searching users:', error);
            searchResults.value = [];
        } finally {
            searchingUsers.value = false;
        }
    }, 300);
};

// Select a user from search results
const selectUser = (user) => {
    selectedReceiver.value = user;
    transferForm.value.receiver_id = user.id;
    receiverSearch.value = `${user.name} (${user.email})`;
    showDropdown.value = false;
    searchResults.value = [];
};

// Clear selected receiver
const clearReceiver = () => {
    selectedReceiver.value = null;
    transferForm.value.receiver_id = null;
    receiverSearch.value = '';
    searchResults.value = [];
};

// Handle blur event with delay to allow click on dropdown
const handleBlur = () => {
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
};

// Load user data
const loadUser = async () => {
    if (!authToken) {
        return;
    }
    try {
        const response = await axios.get('/api/user');
        user.value = response.data;
    } catch (error) {
        console.error('Error loading user:', error);
        if (error.response?.status === 401) {
            // Clear invalid token
            authToken = null;
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common['Authorization'];
        }
    }
};

// Load transactions
const loadTransactions = async () => {
    if (!authToken) {
        loadingTransactions.value = false;
        return;
    }
    loadingTransactions.value = true;
    try {
        const response = await axios.get('/api/transactions');
        balance.value = response.data.balance;
        transactions.value = response.data.transactions.data || response.data.transactions;
    } catch (error) {
        console.error('Error loading transactions:', error);
        if (error.response?.status === 401) {
            // Clear invalid token
            authToken = null;
            localStorage.removeItem('auth_token');
            delete axios.defaults.headers.common['Authorization'];
            message.value = 'Authentication required. Please log in to access your wallet.';
            messageType.value = 'error';
        }
    } finally {
        loadingTransactions.value = false;
    }
};

// Send money
const sendMoney = async () => {
    // Reset errors and messages
    errors.value = { receiver_id: '', amount: '' };
    message.value = '';
    messageType.value = '';

    // Validate form
    if (!transferForm.value.receiver_id || transferForm.value.receiver_id <= 0) {
        errors.value.receiver_id = 'Please select a recipient';
        return;
    }

    if (!transferForm.value.amount || transferForm.value.amount <= 0) {
        errors.value.amount = 'Please enter a valid amount';
        return;
    }

    // Warn if receiver might not be subscribed (for debugging)
    if (transferForm.value.receiver_id !== user.value?.id) {
        console.log('📤 Sending transaction to receiver ID:', transferForm.value.receiver_id);
        console.log('⚠️ Note: Receiver must be subscribed to their channel (private-user.' + transferForm.value.receiver_id + ') to receive real-time updates');
    }

    loading.value = true;

    try {
        const response = await axios.post('/api/transactions', {
            receiver_id: transferForm.value.receiver_id,
            amount: transferForm.value.amount,
        });

        // Update balance
        balance.value = response.data.new_balance;

        // Add transaction to the top of the list
        transactions.value.unshift(response.data.transaction);

        // Show success message
        message.value = response.data.message;
        messageType.value = 'success';

        // Reset form
        transferForm.value = { receiver_id: null, amount: null };
        clearReceiver();

        // Clear message after 5 seconds
        setTimeout(() => {
            message.value = '';
        }, 5000);
    } catch (error) {
        if (error.response?.data?.errors) {
            // Validation errors
            const errorData = error.response.data.errors;
            if (errorData.receiver_id) {
                errors.value.receiver_id = errorData.receiver_id[0];
            }
            if (errorData.amount) {
                errors.value.amount = errorData.amount[0];
            }
        } else {
            // Other errors
            message.value = error.response?.data?.message || 'An error occurred while sending money';
            messageType.value = 'error';
        }
    } finally {
        loading.value = false;
    }
};

// Track subscription state
const isSubscribed = ref(false);
let channelInstance = null;
const lastEventReceived = ref(null);

// Listen for real-time transaction updates
const setupRealtimeListener = () => {
    if (!user.value || !authToken || !echo) {
        console.log('⚠️ Cannot setup listener - user:', !!user.value, 'authToken:', !!authToken, 'echo:', !!echo);
        return;
    }

    const pusher = echo.connector.pusher;
    const channelName = `private-user.${user.value.id}`;
    
    // Helper function to wait for connection
    const waitForConnection = (callback, maxAttempts = 20) => {
        let attempts = 0;
        const checkConnection = () => {
            attempts++;
            if (pusher.connection.state === 'connected') {
                callback();
            } else if (attempts < maxAttempts) {
                setTimeout(checkConnection, 500);
            } else {
                console.error('❌ Pusher connection timeout after', maxAttempts * 500, 'ms');
                pusherConnectionError.value = 'Connection timeout - Pusher failed to connect';
                pusherConnectionStatus.value = 'error';
            }
        };
        checkConnection();
    };

    // Subscribe to channel when Pusher is connected
    const subscribeToChannel = () => {
        try {
            console.log('🔔 Attempting to subscribe to channel:', channelName);
            console.log('Current Pusher state:', pusher.connection.state);
            
            // Ensure we're connected before subscribing
            if (pusher.connection.state !== 'connected') {
                console.warn('⚠️ Pusher not connected, waiting...');
                waitForConnection(subscribeToChannel);
                return;
            }
            
            // Unsubscribe from previous channel if exists
            if (channelInstance) {
                try {
                    echo.leave(channelName);
                } catch (e) {
                    console.warn('Error leaving previous channel:', e);
                }
            }
            
            channelInstance = echo.private(channelName);

            // Listen for subscription success
            channelInstance.subscribed(() => {
                console.log('✅ SUBSCRIBED to channel:', channelName);
                pusherConnectionStatus.value = 'subscribed';
                pusherConnectionError.value = '';
                isSubscribed.value = true;
            });

            // Listen for subscription errors
            channelInstance.error((error) => {
                console.error('❌ Channel subscription error:', error);
                const errorMsg = error?.message || error?.error?.message || JSON.stringify(error);
                pusherConnectionError.value = `Subscription error: ${errorMsg}`;
                isSubscribed.value = false;
                
                // Check if it's an authentication error
                if (errorMsg.includes('auth') || errorMsg.includes('401') || errorMsg.includes('403') || 
                    errorMsg.includes('Unauthorized') || errorMsg.includes('Unauthenticated')) {
                    console.error('❌ Authentication failed for channel subscription');
                    console.error('Debug info:', {
                        'has_token': !!authToken,
                        'token_preview': authToken ? `${authToken.substring(0, 20)}...` : 'none',
                        'channel': channelName,
                        'user_id': user.value?.id,
                    });
                    pusherConnectionError.value = `Authentication failed: ${errorMsg}. Please check your token.`;
                }
            });

            // Debug: Listen to ALL events on this channel to see what's being received
            channelInstance.listenToAll((eventName, data) => {
                const eventInfo = {
                    eventName: eventName,
                    channel: channelName,
                    hasTransaction: !!(data?.transaction),
                    transactionId: data?.transaction?.id,
                    senderId: data?.transaction?.sender_id,
                    receiverId: data?.transaction?.receiver_id,
                    currentUserId: user.value.id,
                    timestamp: new Date().toISOString()
                };
                console.log('📡 [DEBUG] Event received on channel:', eventInfo);
                lastEventReceived.value = eventInfo;
            });

            // Primary event listener
            // When using broadcastAs(), Laravel Echo prefixes private channel events with a dot
            // So 'transaction.completed' becomes '.transaction.completed' on private channels
            channelInstance.listen('.transaction.completed', (data) => {
                console.log('🎉 [PRIMARY LISTENER] Transaction completed event received:', data);
                
                if (!data || !data.transaction) {
                    console.warn('⚠️ Event data missing transaction:', data);
                    return;
                }
                
                const transaction = data.transaction;
                console.log('Processing transaction:', {
                    id: transaction.id,
                    sender_id: transaction.sender_id,
                    receiver_id: transaction.receiver_id,
                    amount: transaction.amount,
                    current_user_id: user.value.id,
                    isSender: transaction.sender_id === user.value.id,
                    isReceiver: transaction.receiver_id === user.value.id
                });

                // Update balance and history if this transaction affects the user
                if (transaction.sender_id === user.value.id || transaction.receiver_id === user.value.id) {
                    console.log('✅ Transaction affects current user, updating UI...');
                    
                    // Check if transaction already exists in the list (to avoid duplicates)
                    const existingIndex = transactions.value.findIndex(t => t.id === transaction.id);
                    
                    if (existingIndex === -1) {
                        // Add transaction to the top of the list immediately
                        console.log('➕ Adding new transaction to list');
                        transactions.value.unshift(transaction);
                    } else {
                        // Update existing transaction
                        console.log('🔄 Updating existing transaction in list');
                        transactions.value[existingIndex] = transaction;
                    }
                    
                    // Update balance immediately from transaction data
                    if (transaction.receiver_id === user.value.id) {
                        // Receiver: balance increases
                        balance.value = parseFloat(balance.value) + parseFloat(transaction.amount);
                        console.log('💰 Updated balance (receiver):', balance.value);
                    } else if (transaction.sender_id === user.value.id) {
                        // Sender: balance decreases (amount + commission)
                        const totalDebit = parseFloat(transaction.amount) + parseFloat(transaction.commission_fee || 0);
                        balance.value = parseFloat(balance.value) - totalDebit;
                        console.log('💰 Updated balance (sender):', balance.value);
                    }
                    
                    // Reload user data and transactions to get updated balance from server
                    Promise.all([
                        loadUser(),
                        loadTransactions()
                    ]).catch(error => {
                        console.error('❌ Error refreshing data after transaction:', error);
                        // Fallback: just reload transactions
                        loadTransactions();
                    });
                } else {
                    console.log('ℹ️ Transaction does not affect current user');
                }
            });

            // Fallback: Also listen without the dot prefix (in case Laravel Echo doesn't add it)
            channelInstance.listen('transaction.completed', (data) => {
                console.log('🎉 [FALLBACK LISTENER] Transaction completed event received (no dot prefix):', data);
                // Same handling as primary listener
                if (data && data.transaction) {
                    const transaction = data.transaction;
                    if (transaction.sender_id === user.value.id || transaction.receiver_id === user.value.id) {
                        const existingIndex = transactions.value.findIndex(t => t.id === transaction.id);
                        if (existingIndex === -1) {
                            transactions.value.unshift(transaction);
                        } else {
                            transactions.value[existingIndex] = transaction;
                        }
                        
                        // Update balance
                        if (transaction.receiver_id === user.value.id) {
                            balance.value = parseFloat(balance.value) + parseFloat(transaction.amount);
                        } else if (transaction.sender_id === user.value.id) {
                            const totalDebit = parseFloat(transaction.amount) + parseFloat(transaction.commission_fee || 0);
                            balance.value = parseFloat(balance.value) - totalDebit;
                        }
                        
                        Promise.all([loadUser(), loadTransactions()]).catch(() => loadTransactions());
                    }
                }
            });

            console.log('✅ Event listeners set up for channel:', channelName);

        } catch (error) {
            console.error('❌ Error setting up real-time listener:', error);
            pusherConnectionError.value = `Listener setup error: ${error.message}`;
            isSubscribed.value = false;
        }
    };

    // Start subscription process
    if (pusher.connection.state === 'connected') {
        subscribeToChannel();
    } else {
        console.log('⏳ Waiting for Pusher connection... Current state:', pusher.connection.state);
        
        // Set up one-time connection listener
        const connectionHandler = () => {
            console.log('✅ Pusher connected, subscribing to channel now...');
            pusher.connection.unbind('connected', connectionHandler);
            subscribeToChannel();
        };
        
        pusher.connection.bind('connected', connectionHandler);
        
        // Also set a timeout fallback
        waitForConnection(() => {
            pusher.connection.unbind('connected', connectionHandler);
            subscribeToChannel();
        });
    }
};

// Initialize
onMounted(async () => {
    // Check if we have a valid auth token
    if (authToken && authToken !== 'null' && authToken !== '') {
        try {
            console.log('🚀 Initializing wallet app...');
            
            // Step 1: Initialize Echo first
            console.log('Step 1: Initializing Echo...');
            initializeEcho();
            
            // Step 2: Load user data (required for channel subscription)
            console.log('Step 2: Loading user data...');
            await loadUser();
            
            if (!user.value) {
                throw new Error('Failed to load user data');
            }
            
            // Step 3: Setup real-time listener (subscription happens here)
            // This MUST happen before any transactions can be sent
            console.log('Step 3: Setting up real-time listener (subscription)...');
            setupRealtimeListener();
            
            // Step 4: Load transactions
            console.log('Step 4: Loading transaction history...');
            await loadTransactions();
            
            console.log('✅ Wallet app initialized successfully');
        } catch (error) {
            console.error('❌ Error initializing wallet:', error);
            message.value = 'Error loading wallet data. Please refresh the page.';
            messageType.value = 'error';
        }
    } else {
        // Show message if not authenticated
        console.warn('⚠️ No auth token found');
        message.value = 'Please log in to use the wallet.';
        messageType.value = 'error';
    }
});

onUnmounted(() => {
    console.log('🧹 Cleaning up Echo connection...');
    
    // Leave channel if subscribed
    if (channelInstance) {
        try {
            const channelName = `private-user.${user.value?.id}`;
            echo.leave(channelName);
            console.log('✅ Left channel:', channelName);
        } catch (error) {
            console.warn('Error leaving channel:', error);
        }
        channelInstance = null;
    }
    
    // Disconnect Echo
    if (echo) {
        try {
            echo.disconnect();
            console.log('✅ Echo disconnected');
        } catch (error) {
            console.error('Error disconnecting Echo:', error);
        }
        echo = null;
    }
    
    isSubscribed.value = false;
});
</script>

