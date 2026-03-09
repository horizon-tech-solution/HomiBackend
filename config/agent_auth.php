<?php

require_once __DIR__ . '/user_auth.php';

// AgentAuth is just UserAuth — agents are users with role = 'agent'
class AgentAuth extends UserAuth {}