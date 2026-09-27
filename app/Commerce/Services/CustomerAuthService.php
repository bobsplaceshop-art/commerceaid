<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Core\Session;
use CorporateAid\Platform\Support\Hasher;

/**
 * Customer authentication service.
 *
 * Parallel to CorporateAid\Core\Auth but operating on the customer
 * table and the customer session. The two services do not share
 * state:
 *
 *   Core\Auth              uses Session::class       (ca_session)
 *   CustomerAuthService    uses 'customer_session'   (ca_customer_session)
 *
 * Same browser can have an admin logged in and a customer logged in
 * simultaneously without collision. The two cookies carry independent
 * session IDs; the two session stores are separate PHP files.
 *
 * Differences from admin Auth:
 *   - No permission checks. Customers have no RBAC.
 *   - Login does not require approval_status = 'approved'. A pending
 *     customer can log in and browse; only checkout is gated (by
 *     Customer::canCheckout()).
 *   - isActive() is the only status gate. A disabled or archived
 *     customer cannot log in.
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */
final class CustomerAuthService
{
    /**
     * Session key holding the logged-in customer's id.
     *
     * Distinct from Core\Auth's SESSION_USER_KEY ('auth_user_id') for
     * readability. The two services also use different sessions, so
     * the keys never share a store; the distinct names exist so a
     * future debugger glancing at session contents knows immediately
     * which service wrote the value.
     */
    private const SESSION_CUSTOMER_KEY = 'customer_id';

    private CustomerRepository $customers;
    private Session $session;
    private ?Customer $cached = null;

    public function __construct(CustomerRepository $customers, Session $session)
    {
        $this->customers = $customers;
        $this->session   = $session;
    }

    /**
     * Attempt a login. Returns the Customer on success, null on
     * failure.
     *
     * Does not distinguish "wrong email" from "wrong password" from
     * "account disabled" in the return value. All failure modes
     * return null; the caller shows a generic error.
     */
    public function attempt(string $email, string $password): ?Customer
    {
        $customer = $this->customers->findByEmail($email);

        if ($customer === null) {
            // Timing defense: run a dummy hash verification so an
            // attacker cannot distinguish "no such email" from
            // "wrong password" by response time. Same pattern as
            // Core\Auth::attempt().
            Hasher::verify(
                $password,
                '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'
            );
            return null;
        }

        if (!$customer->isActive()) {
            // Disabled or archived. Not a login-eligible state.
            // Run the dummy hash anyway to keep timing consistent
            // with the not-found case above.
            Hasher::verify(
                $password,
                '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'
            );
            return null;
        }

        if ($customer->passwordHash === null || $customer->passwordHash === '') {
            // Customer registered as a guest (no password) or the
            // password field was never populated. Cannot verify.
            Hasher::verify(
                $password,
                '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'
            );
            return null;
        }

        if (!Hasher::verify($password, $customer->passwordHash)) {
            return null;
        }

        // Password correct. Rehash if the algorithm has upgraded.
        if (Hasher::needsRehash($customer->passwordHash)) {
            $this->customers->setPassword($customer->id, Hasher::hash($password));
        }

        $this->login($customer);
        $this->customers->updateLastLogin($customer->id);

        return $customer;
    }

    /**
     * Log a customer in directly. Used after a successful attempt,
     * and by registration (auto-login after signup).
     */
    public function login(Customer $customer): void
    {
        $this->session->regenerate(true);
        $this->session->set(self::SESSION_CUSTOMER_KEY, $customer->id);
        $this->cached = $customer;
    }

    /**
     * Log the current customer out. Idempotent: calling on an
     * already-logged-out session is a no-op.
     */
    public function logout(): void
    {
        $this->session->forget(self::SESSION_CUSTOMER_KEY);
        $this->session->regenerate(true);
        $this->cached = null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user?->id;
    }

    /**
     * The currently authenticated customer, or null.
     *
     * Cached per-request so repeated calls in one controller action
     * do not re-query the database.
     *
     * If the session references a customer who no longer exists or
     * is no longer active, the session is cleared and null is
     * returned. A stale session never survives to a controller.
     */
    public function user(): ?Customer
    {
        if ($this->cached instanceof Customer) {
            return $this->cached;
        }

        $id = $this->session->get(self::SESSION_CUSTOMER_KEY);
        if (!is_int($id) || $id <= 0) {
            return null;
        }

        $customer = $this->customers->findById($id);
        if ($customer === null || !$customer->isActive()) {
            $this->logout();
            return null;
        }

        $this->cached = $customer;
        return $customer;
    }
}
