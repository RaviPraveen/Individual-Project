**System bug review and proposed fix plan — 14 October 2026**

**Follow-up:** The selected workflow issues have now been fixed: Quick Order quantities, exact barcode/SKU search, duplicate customer phone validation, deletion of records with history, admin display access, promotion date status, refund preview, filtered dead-stock advice, month-end dates, duplicate display windows, and outdated promotion posters. Verification passed: 127 tests and 482 assertions, plus cart/refund JavaScript checks. The major issues were left unchanged as requested. The findings below describe the original review.

This is a review of the current project. Application code has not been changed during this review. Earlier comment and naming changes remain in place.

The review covered authentication and staff permissions, checkout, customer refunds, supplier returns, products and stock, customer/supplier/user management, reports, promotions, customer display, and the Hugging Face features. The existing suite passed: **112 tests, 396 assertions**. Additional temporary checks examined **25 backend scenarios and 4 JavaScript functions**. They reproduced the behavior described below; they are diagnostic probes, not tests saying the faulty behavior is correct.

Backend probes used SQLite in memory, fake/test configuration, and blocked outgoing HTTP requests. JavaScript checks executed extracted functions with small DOM stubs. No live customer records were changed. MySQL concurrency, a full browser workflow, and live Hugging Face availability were not tested. The stock race below was reproduced by explicitly simulating an interleaving of two operations. This review identifies concrete defects and remaining risks; it cannot guarantee there are no other bugs.

**Fix first: access, money, and stock**

1. **Public registration gives an outsider a cashier account — high priority, reproduced.**
   Anyone who can reach the registration page can register, log in, and open billing. The database defaults new accounts to an active cashier. For a staff POS, this allows access without an administrator creating or approving the account.
   **Fix:** close public staff registration and use the existing administrator user-management page. If signup is intentionally needed, introduce an approval step and deny POS access until approval.
   **Check after fixing:** an unauthenticated visitor cannot create an active cashier; an administrator can still create staff.
   Evidence: [registration routes](C:/xampp/htdocs/Individual_Projct/routes/auth.php:15), [account creation](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Auth/RegisteredUserController.php:30), [database defaults](C:/xampp/htdocs/Individual_Projct/database/migrations/2026_07_19_094418_add_role_and_is_active_to_users_table.php:13).

2. **Names and messages can be interpreted as HTML — high priority, code traced and JavaScript behavior reproduced.**
   Product/customer names are inserted directly into `innerHTML` in billing, and toast messages are also inserted as HTML. A harmless name containing bold markup was rendered as markup rather than plain text. The same unsafe output path can execute malicious event-handler markup in another staff member's browser. The database input is treated as page code.
   **Fix:** insert names, SKUs, phone numbers, and messages using `textContent`, or consistently escape dynamic values before assembling HTML. Keep static icons and layout markup separate.
   **Check after fixing:** markup-like names display literally in search results, carts, return forms, and toasts.
   Evidence: [product results](C:/xampp/htdocs/Individual_Projct/resources/views/cashier/billing/index.blade.php:282), [customer results](C:/xampp/htdocs/Individual_Projct/resources/views/cashier/billing/index.blade.php:650), [toasts](C:/xampp/htdocs/Individual_Projct/public/js/pos.js:130).

3. **Changing tax changes old refunds — high priority, reproduced.**
   A customer pays Rs 100 plus Rs 10 tax. After the administrator changes tax to 0%, returning that item refunds only Rs 100. The reverse change can over-refund. Returns read today's tax setting instead of using the original sale's tax amount.
   **Fix:** allocate the tax recorded on the original sale to the returned items. Preserve original discount and redemption amounts too. Do not recalculate old sales using current settings.
   **Check after fixing:** changing tax up or down does not change the refundable amount of an existing sale.
   Evidence: [refund calculation](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/ReturnController.php:108).

4. **One sold item can be refunded twice in one request — high priority, reproduced.**
   A sale contains one Rs 100 item. A request containing that sale item twice is accepted, refunds Rs 200, and restores two units. The normal form creates one row per item, but the server does not enforce this. Each row is checked before any of the new return rows are saved.
   **Fix:** reject duplicate sale-item IDs, or combine their quantities before checking the remaining returnable quantity under the sale lock.
   **Check after fixing:** duplicate entries cannot exceed the original quantity or refund value, and rejected returns change neither stock nor money.
   Evidence: [return row validation](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/ReturnController.php:119).

5. **Supplier returns can make stock negative — high priority, reproduced.**
   Stock is 10. The same product is entered on two supplier-return rows, with quantity 6 on each. Both checks compare 6 against 10; completion removes 12 and leaves **−2**.
   **Fix:** total quantities per product before checking stock, both when creating the draft and when completing it. Keep completion inside the existing transaction and locks.
   **Check after fixing:** two rows totaling more than available stock are rejected without changing the return status or stock.
   Evidence: [supplier-return completion](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/SupplierReturnController.php:113).

6. **A stock adjustment can overwrite a sale's stock reduction — high priority, simulated interleaving reproduced.**
   The adjustment loads stock 10. A cashier sells 2, leaving 8. The adjustment then adds 5 to its old value of 10 and saves 15. Correct stock is 13. The adjustment is not enclosed in a transaction and does not reload/lock the product. Ordinary product edits and CSV imports also overwrite stock directly without recording the stock difference.
   **Fix:** centralize stock changes in a transaction that reads the locked current row, applies the change, and writes its stock movement. Protect stale edit forms and make import adjustments explicit.
   **Check after fixing:** the example finishes at 13, and every applied stock change has a matching movement. Run an actual simultaneous-operation test on MySQL during implementation.
   Evidence: [stock adjustment](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/ProductController.php:94), [product update](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/ProductController.php:58), [CSV stock overwrite](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/ProductController.php:209).

7. **Separate partial refunds can exceed the original payment by rounding — medium priority, reproduced.**
   Two items at Rs 1.05 with 10% tax cost Rs 2.31. Returning them separately produces Rs 1.16 each, totaling Rs 2.32. Each return rounds its tax independently.
   **Fix:** allocate original amounts in cents, track amounts already refunded, and assign any remaining rounding difference to the final eligible refund. The sum of item refunds must not exceed the original refundable item payment.
   **Check after fixing:** returning together and returning in parts produce the same total for tax, discount, and redemption allocations.
   Evidence: [per-return rounding](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/ReturnController.php:132).

8. **Split returns leave earned loyalty points behind — medium priority, reproduced.**
   Two Rs 50 items earn 1 point. Returning one item reverses `floor(0.5) = 0` points. Returning the other reverses another 0. The customer has returned everything but keeps the point.
   **Fix:** calculate the cumulative points reversal for all returns on the sale, then subtract points already reversed. Define separately what happens when earned points have already been spent.
   **Check after fixing:** a full return reverses the original earned points whether done in one return or several, subject to the agreed spent-points policy.
   Evidence: [points reversal](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/ReturnController.php:160).

**Checkout and everyday workflow bugs**

9. **Displayed amounts can disagree with the saved amounts — medium priority, reproduced.**
   The return screen estimates only quantity × original unit price. A Rs 100 item with a Rs 10 discount and Rs 9 tax should refund Rs 99, but the preview says Rs 100. Separately, the billing browser rounds differently from PHP: Rs 1.05 with 10% discount and 10% tax displays Rs 1.04 but saves Rs 1.03.
   **Fix:** make previews use the same monetary allocation and rounding rules as the server, including tax, discounts, and points. Prefer a shared server calculation for return quotes.
   **Check after fixing:** checkout display, receipt, refund preview, and saved return agree.
   Evidence: [return preview](C:/xampp/htdocs/Individual_Projct/resources/views/returns/_content.blade.php:238), [billing preview](C:/xampp/htdocs/Individual_Projct/resources/views/cashier/billing/index.blade.php:507).

10. **Deactivated products can still be sold from an existing cart — medium priority, reproduced.**
    Search hides inactive products, but checkout checks only existence and stock. If the administrator deactivates a product after a cashier adds it to the cart, submission still completes the sale.
    **Fix:** check that every product is active when the locked checkout calculation runs; report which item must be removed.
    **Check after fixing:** deactivate an item after adding it to a cart; checkout rejects it without deducting stock.
    Evidence: [checkout product loading](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Cashier/BillingController.php:84).

11. **A duplicate customer phone number causes a server error — medium priority, reproduced.**
    The database requires unique phone numbers, but the administrator create/edit form does not validate uniqueness. Using an existing number returns HTTP 500 instead of a useful form error. Quick-create already has a uniqueness rule.
    **Fix:** validate uniqueness in administrator create and edit, excluding the current customer during edits; retain the database constraint.
    **Check after fixing:** duplicate numbers show a field error; saving a customer's unchanged number works.
    Evidence: [customer validation](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/CustomerController.php:136).

12. **Some delete buttons cause server errors — medium priority, three cases reproduced.**
    Deleting a product with purchase-order history but no sales returns HTTP 500. So does deleting a supplier with supplier returns but no purchase orders, or deleting a user with AI logs but no transactions. The delete guards do not cover all database references.
    **Fix:** check all relevant references and deactivate/archive or show a clear refusal. Preserve historical records; do not solve this by deleting transaction history.
    **Check after fixing:** each referenced record gets the intended message or archival result, while unused records can still be deleted.
    Evidence: [product delete](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/ProductController.php:81), [supplier delete](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/SupplierController.php:48), [user reference check](C:/xampp/htdocs/Individual_Projct/app/Models/User.php:67).

13. **Background requests receive HTML/redirects instead of validation errors — medium priority, reproduced.**
    An invalid JSON quick-create request receives HTTP 302 rather than a JSON 422 response. The browser expects JSON, follows the redirect, then fails to read the resulting HTML. The global exception rule only treats `/api/*` as JSON, although these endpoints are web routes. Billing's fetch helper also overwrites its default Accept header when callers provide headers.
    **Fix:** preserve Laravel's JSON detection (`expectsJson`) alongside any path rule, and merge request headers correctly.
    **Check after fixing:** invalid quick-create, AI, display-sync, and poster requests return structured field errors; ordinary forms still redirect normally.
    Evidence: [exception rendering](C:/xampp/htdocs/Individual_Projct/bootstrap/app.php:26), [fetch helper](C:/xampp/htdocs/Individual_Projct/resources/views/cashier/billing/index.blade.php:258).

14. **A valid barcode can appear to be missing — medium priority, reproduced.**
    Search sorts all partial matches alphabetically and returns only 10. If 10 other names contain the scanned digits, the actual exact barcode match can fall outside those results. The scanner then cannot find it.
    **Fix:** give exact barcode/SKU matches priority, or add an exact scan lookup separate from text search.
    **Check after fixing:** an exact barcode is returned even with more than 10 partial matches.
    Evidence: [product search](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/ProductController.php:239).

15. **AI Quick Order replaces an existing cart quantity — medium priority, JavaScript behavior reproduced.**
    The cart already has 2 units. Adding 3 units from AI Quick Order leaves 3, although the action says it adds the items. It first adds one unit, then overwrites the quantity with the AI quantity.
    **Fix:** add the confirmed quantity to the existing quantity, apply the stock limit once, and explain any capped quantity.
    **Check after fixing:** 2 existing + 3 added becomes 5 when enough stock is available.
    Evidence: [AI cart addition](C:/xampp/htdocs/Individual_Projct/resources/views/cashier/billing/index.blade.php:792).

16. **The admin POS has a customer-display button it cannot use — medium priority, reproduced.**
    Administrators can open billing, which shows the display button, but display routes permit only cashiers and return HTTP 403. Also, the button has both an inline opener and a click listener using different window names, which can attempt to open two windows; actual popup behavior depends on the browser.
    **Fix:** make display access match the intended admin POS permissions, and use one click handler. If display access is intentionally cashier-only, hide/disable this function for administrators with a clear explanation.
    **Check after fixing:** the intended roles can open and synchronize exactly one customer display.
    Evidence: [button](C:/xampp/htdocs/Individual_Projct/resources/views/cashier/billing/index.blade.php:11), [second handler](C:/xampp/htdocs/Individual_Projct/resources/views/cashier/billing/index.blade.php:563), [route permissions](C:/xampp/htdocs/Individual_Projct/routes/web.php:165).

**Promotions, reports, and recommendations**

17. **An expired promotion can stay expired after its dates are extended — medium priority, reproduced.**
    Extend an expired promotion so that today is inside its new date range. It still has status `expired`, and the synchronization code never reactivates expired records. It stays off the display.
    **Fix:** derive the status again when dates change, preserving an explicit manual pause. Keep counters and visibility consistent with the effective dates.
    **Check after fixing:** expired → current and active → future edits produce the correct status and display behavior.
    Evidence: [promotion update](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/PromotionController.php:126), [status synchronization](C:/xampp/htdocs/Individual_Projct/app/Models/Promotion.php:96).

18. **Month-end revenue charts and comparisons use incorrect months — medium priority, reproduced.**
    On March 31, subtracting a month can overflow February and produce another March entry. The chart repeats months and omits February. The previous-month comparison also adds 30 days to February 1, extending into March and counting current-month sales as previous-month sales.
    **Fix:** build chart periods from month starts, use date operations without month overflow, and cap comparison ranges at the previous month's end. Review leap-year comparisons at the same time.
    **Check after fixing:** March 31 and other month ends produce 12 distinct monthly labels and non-overlapping comparison periods.
    Evidence: [comparison range](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/RevenueController.php:42), [monthly chart](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/RevenueController.php:229).

19. **Promotion revenue for a month can exclude that month's sales — medium priority, reproduced; related aggregation risks code traced.**
    A promotion runs September–November and sells Rs 100 in October. Its lifetime metric is Rs 100, but October's promotion revenue is 0 because neither endpoint falls in October. Conversely, a campaign ending this month can contribute its whole lifetime revenue. Overlapping promotions for the same product can also count the same sale multiple times in the combined total.
    **Fix:** filter sales by the intersection of campaign dates and the selected month. Decide how sales are attributed when campaigns overlap, and avoid counting the same sale twice in an overall figure. Label inferred campaign sales accurately.
    **Check after fixing:** campaigns spanning months and overlapping campaigns give correct, explainable monthly totals.
    Evidence: [promotion summary](C:/xampp/htdocs/Individual_Projct/app/Services/PromotionAnalytics.php:49).

20. **AI recommendations can describe the wrong filtered products — medium priority, reproduced.**
    View a dead-stock report for category A, then category B with the same number of rows. Category B can show category A's AI recommendation because the cache key uses row count and time, without the selected products or filters.
    **Fix:** include the filter values and a stable fingerprint of the actual data sent to Hugging Face in the cache key. Apply the same principle to other data-dependent recommendation caches.
    **Check after fixing:** equal-sized but different result sets get their own recommendations; unchanged inputs can reuse a cached answer.
    Evidence: [dead-stock cache key](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/ReportController.php:277).

**Additional findings from code inspection**

These are concrete code-path concerns, but they did not receive full end-to-end reproduction during this review.

- **Repeated checkout submissions:** identical requests create two sales and deduct stock twice, as confirmed by a backend probe. There is no request identifier to distinguish a retry from a new sale, and the button is not locked after submission. Add a unique checkout token enforced server-side plus button disabling. Verify retries return the original receipt while intentionally new checkouts remain possible. [Checkout submission](C:/xampp/htdocs/Individual_Projct/resources/views/cashier/billing/index.blade.php:682).
- **Invoice/return numbering under simultaneous use:** generating the next number by reading the last record and checking existence is not atomic. Two disjoint transactions can choose the same number; the unique constraint prevents duplicate records but one operation can fail. Use an atomic counter/sequence or a collision-safe identifier and test simultaneous operations on MySQL. [Invoice number generator](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Cashier/BillingController.php:412), [return number generator](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/ReturnController.php:243).
- **Poster content can disagree with the saved promotion:** a draft image is saved without a fingerprint of its product/title/price. Changing those fields before saving can attach the old poster to the new values. Editing a saved offer also leaves the existing image unchanged. Track the values used to generate the poster and require regeneration/reapproval after relevant edits. [Draft creation](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/PromotionPosterController.php:108), [draft adoption](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/PromotionController.php:88).
- **Cancelled purchase orders influence supplier recommendations:** the resolver chooses the newest order without filtering its status. A cancelled order from supplier B can override the supplier A that actually supplied the product. Use received orders for supply history, with an explicitly labeled fallback if needed. [Supplier resolver](C:/xampp/htdocs/Individual_Projct/app/Services/ProductSupplierResolver.php:17).
- **The currency setting is saved but not applied:** the value is read back on the settings screen, while the POS and reports hard-code `Rs`. Either apply the configured display symbol consistently or remove the unsupported setting. This is a display setting, not currency conversion. [Setting controller](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/SettingController.php:17).
- **Dashboard Banner is offered as a promotion target but has no renderer:** the only promotion display consumer loads `customer_display`; the dashboard renders summary cards, not the chosen banners. Implement the dashboard banner or remove this target until supported. [Target selector](C:/xampp/htdocs/Individual_Projct/resources/views/admin/promotions/_form.blade.php:166).
- **Report dates are parsed without validation:** malformed dates can throw instead of producing a helpful error, and reversed ranges are not rejected. Validate dates and require the end to be on/after the start before querying. [Report date helper](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Concerns/BuildsReportResponses.php:12).
- **Dashboard Active Products counts inactive products too:** the controller uses `Product::count()` for a card labeled Active Products. Apply the active filter and use consistent low-stock filters. [Dashboard totals](C:/xampp/htdocs/Individual_Projct/app/Http/Controllers/Admin/DashboardController.php:29).

**Business rules to settle before changing their behavior**

- **Gross sales versus money retained:** a reproduced example sells a Rs 100 item for Rs 90 after discount, then refunds Rs 90. The revenue module still shows Rs 100 revenue and Rs 50 profit for a Rs 50 cost. It calculates original line totals minus original costs. Those are pre-discount, pre-return figures; they must not be presented as money retained or realized profit. Recommended design: clearly labeled original sales, discounts, refunds, net item revenue, and corresponding returned-cost adjustments. Keep tax, bag fees, loyalty funding, and the date on which refunds affect reports explicit. Preserve useful gross-sale statistics rather than silently replacing every figure with one different definition.
- **Promotion price at checkout:** an active offer of Rs 80 for a Rs 100 product still charges Rs 100. This was reproduced. If promotions promise actual sale prices, checkout and receipts must apply and snapshot the eligible offer. If promotions are only signage managed separately, the workflow must explicitly enforce or explain how the advertised price is honored. Decide overlap priority and whether invoice discounts stack before implementation.
- **Redeemed loyalty points on a return:** paying entirely with 100 points and returning the item currently gives Rs 0 and restores 0 points. This was reproduced. Recommended rule: return the applicable redeemed points and refund only the actual cash/card share, while reversing earned points proportionally. Define partial returns and already-spent earned points so the customer is not credited twice. Bag-fee refund eligibility and whether damaged returned goods should re-enter saleable stock also require an explicit policy.

**Implementation order**

1. **Close access and HTML rendering defects:** public registration and unsafe dynamic rendering. Add focused access and rendering checks.
2. **Fix the complete refund calculation together:** original-sale tax, duplicate entries, cumulative monetary rounding, cumulative earned-points reversal, and accurate previews. Apply the agreed redeemed-points policy in this step. Keep changes understandable in a small shared calculation component.
3. **Fix stock integrity and checkout reliability:** aggregate supplier returns, make all stock adjustments transactional and auditable, reject inactive items, protect duplicate submissions, and make document numbers safe under concurrent use.
4. **Fix daily workflow errors:** duplicate-phone validation, deletion guards, JSON errors, exact barcode lookup, AI cart quantities, and display permissions/handlers.
5. **Fix reporting and promotions:** month boundaries, campaign revenue windows, edited promotion status, cached narratives, and poster consistency. Apply the agreed reporting and promotion-pricing rules, including historical price snapshots for new transactions.
6. **Complete smaller settings and input fixes**, then verify the full user journey.

**Acceptance checks for the completed work**

- Existing tests continue to pass, with new regression tests asserting the corrected behavior.
- A sale and all its partial returns reconcile to the original refundable amounts and stock quantities.
- A failed operation makes no partial stock, loyalty, sale, or return changes.
- Simultaneous cashier and administrator operations are checked on MySQL, not inferred from SQLite.
- A browser walkthrough covers login, scan/search, customer selection, payment, receipt/PDF, partial refund, supplier return, promotion editing/display, and report filtering.
- Hugging Face success, unavailable-service fallback, invalid output, and cached recommendations are tested with fakes first. A live API check is separate from the correctness of the POS.

Temporary diagnostic files: [backend probes](C:/Users/Praveen/AppData/Local/Temp/FoodcityAuditTest.php), [JavaScript probes](C:/Users/Praveen/AppData/Local/Temp/foodcity-ui-audit.cjs). These intentionally assert existing defects to demonstrate them; convert relevant cases into correct-behavior regression tests during implementation rather than adding these probes unchanged to the project's test suite.
