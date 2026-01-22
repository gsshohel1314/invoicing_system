<p>প্রিয় {{ $invoice->vtsAccount->name }},</p>

<p>আপনার নতুন ইনভয়েস #{{ $invoice_number }} তৈরি হয়েছে।</p>
<p><strong>টোটাল:</strong> {{ $total_amount }} ৳</p>
<p><strong>Due Date:</strong> {{ $due_date }}</p>

<p>ইনভয়েস PDF ডাউনলোড করতে নিচের লিঙ্কে ক্লিক করুন:</p>

<a href="{{ $downloadUrl }}" style="background:#007bff; color:white; padding:12px 24px; text-decoration:none; border-radius:5px; display:inline-block;">
    ইনভয়েস ডাউনলোড করুন
</a>

<p>এই লিঙ্ক ৭ দিন পর্যন্ত কাজ করবে।</p>

<p>ধন্যবাদ,<br>iTracker Team</p>