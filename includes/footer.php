<?php ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');

.sg-footer{
  font-family:'Outfit',sans-serif;
  background:linear-gradient(135deg, var(--bg) 0%, var(--bg-dark) 60%, var(--bg-deeper) 100%);
  color:#e2e8f0;padding:56px 0 0;
  position:relative;overflow:hidden;
}
.sg-footer::before{
  content:'';position:absolute;top:-100px;right:-80px;
  width:340px;height:340px;
  background:radial-gradient(circle,rgba(234,88,12,0.1),transparent 70%);
  border-radius:50%;pointer-events:none;
}
.sg-footer::after{
  content:'';position:absolute;bottom:80px;left:-60px;
  width:260px;height:260px;
  background:radial-gradient(circle,rgba(16,185,129,0.07),transparent 70%);
  border-radius:50%;pointer-events:none;
}
.sg-inner{max-width:1140px;margin:0 auto;padding:0 28px;position:relative;z-index:1;}

/* SPORTS BAR */
.sports-bar{
  display:flex;gap:8px;flex-wrap:wrap;
  margin-bottom:40px;padding-bottom:28px;
  border-bottom:1px solid rgba(255,255,255,0.07);
}
.sport-chip{
  display:flex;align-items:center;gap:6px;
  padding:7px 14px;background:rgba(255,255,255,0.05);
  border:1px solid rgba(255,255,255,0.09);border-radius:30px;
  font-size:12.5px;color:#94a3b8;cursor:pointer;
  transition:.2s;text-decoration:none;
}
.sport-chip:hover{background:rgba(234,88,12,0.15);border-color:rgba(234,88,12,0.4);color:#fb923c;}
.sport-chip i{font-size:13px;color:#f97316;}

/* MAIN GRID */
.sg-grid{
  display:grid;
  grid-template-columns:1.7fr 1fr 1fr 1.25fr;
  gap:36px;
}

/* BRAND */
.brand-logo{display:flex;align-items:center;gap:11px;margin-bottom:15px;text-decoration:none;}
.brand-logo img{width:46px;height:46px;border-radius:12px;border:2px solid rgba(251,146,60,0.35);object-fit:cover;}
.brand-logo-txt{display:flex;flex-direction:column;line-height:1.1;}
.brand-name{font-size:22px;font-weight:800;background:linear-gradient(90deg, #39fd08, #e4dfdb, #4fae01);-webkit-background-clip:text;color:transparent;}
.brand-tag{font-size:10px;color: #f9fafb;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;}
.brand-desc{font-size:13px;color: #65f815;line-height:1.75;margin-bottom:20px;max-width:260px;}

.brand-stats{display:flex;gap:16px;margin-bottom:22px;}
.bstat{text-align:center;}
.bstat-num{font-size:18px;font-weight:700;color: rgb(249, 248, 250);}
.bstat-lbl{font-size:10.5px;color: #eff0f3;text-transform:uppercase;letter-spacing:.8px;}

.social-label{font-size:10.5px;font-weight:700;color: #29f804;text-transform:uppercase;letter-spacing:1px;margin-bottom:9px;}
.social-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;}
.sbtn{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;text-decoration:none;transition:.25s;border:1px solid rgb(247, 243, 243);background:rgba(255,255,255,0.05);color: #c0f1ad;}
.sbtn:hover{transform:translateY(-3px);color: #fff;}
.sbtn.fb:hover{background:#1877f2;border-color:#1877f2;}
.sbtn.ig:hover{background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);border-color:#e6683c;}
.sbtn.yt:hover{background:#ff0000;border-color:#ff0000;}
.sbtn.tw:hover{background:#000;border-color:#fff;}
.sbtn.tk:hover{background:#010101;border-color:#69c9d0;}
.sbtn.wa:hover{background:#25d366;border-color:#25d366;}

/* NEWSLETTER */
.nl-box{background:rgba(238, 236, 238, 0.99);border:1px solid rgb(61, 243, 6);border-radius:14px;padding:15px;}
.nl-box strong{color: #0831fc;font-size:13px;display:block;margin-bottom:5px;}
.nl-box p{font-size:12px;color: rgb(243, 243, 244);margin-bottom:10px;line-height:1.5;}
.nl-form{display:flex;gap:6px;}
.nl-form input{flex:1;padding:9px 12px;background:rgba(242, 238, 237, 0.95);border:1px solid rgba(250, 78, 4, 0.98);border-radius:9px;font-size:12.5px;color: #0606f9;outline:none;font-family:'Outfit',sans-serif;transition:.2s;}
.nl-form input::placeholder{color: #84888e;}
.nl-form input:focus{border-color:#f97316;background:rgba(249,115,22,0.1);}
.nl-form button{padding:9px 13px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border:none;border-radius:9px;font-size:12px;cursor:pointer;font-family:'Outfit',sans-serif;font-weight:600;transition:.2s;}
.nl-form button:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(234,88,12,0.4);}
.nl-msg{font-size:11.5px;margin-top:6px;}

/* FOOTER COLS */
.fc h4{font-size:13.5px;font-weight:700;color: #40fc07;margin-bottom:17px;position:relative;padding-bottom:10px;}
.fc h4::after{content:'';position:absolute;bottom:0;left:0;width:26px;height:2px;background:linear-gradient(100deg, #4afd08, #ec5e94);border-radius:2px;}
.fl{list-style:none;display:flex;flex-direction:column;gap:9px;}
.fl li a{color: #fafcfe;text-decoration:none;font-size:13px;display:flex;align-items:center;gap:7px;transition:.2s;}
.fl li a i{font-size:9px;color: #05f40d;transition:.2s;}
.fl li a:hover{color: #190eed;padding-left:4px;}
.fl li a:hover i{color: #c9cfc7;}

/* CONTACT */
.ci{display:flex;flex-direction:column;gap:13px;}
.citem{display:flex;align-items:flex-start;gap:11px;}
.cicon{width:34px;height:34px;min-width:34px;border-radius:9px;background:rgb(73, 233, 4);border:1px solid rgba(255, 6, 243, 0.99);display:flex;align-items:center;justify-content:center;font-size:13px;color: rgb(247, 245, 243);}
.ctxt{font-size:12.5px;color: #38f603;line-height:1.55;}
.ctxt strong{color: #f7f9f7;font-size:11.5px;display:block;margin-bottom:2px;text-transform:uppercase;letter-spacing:.7px;}

/* PAYMENT */
.pay-row{display:flex;gap:7px;flex-wrap:wrap;margin-top:4px;}
.pay-pill{display:flex;align-items:center;gap:5px;padding:4px 10px;background:rgba(48, 246, 3, 0.99);border:1px solid rgba(249, 254, 246, 0.99);border-radius:6px;font-size:11px;color: rgb(244, 245, 247);}
.pay-pill i{color: #f97316;font-size:11px;}

/* DIVIDER & BOTTOM */
.sg-divider{border-top:1px solid rgba(66, 252, 3, 0.99);margin-top:38px;}
.sg-bottom{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:16px 28px 22px;max-width:1140px;margin:0 auto;}
.sg-copy{font-size:12px;color: #f5f7fa;}
.sg-copy span{color: #30f803;font-weight:600;}
.sg-badges{display:flex;gap:7px;flex-wrap:wrap;}
.sg-badge{display:flex;align-items:center;gap:5px;background:rgba(255, 255, 255, 0.97);border:1px solid rgb(94, 242, 2);border-radius:20px;padding:4px 11px;font-size:10.5px;color:#64748b;}
.sg-badge i{font-size:11px;}
.sg-badge.grn i{color: #22c55e;}
.sg-badge.org i{color: #f97316;}
.sg-badge.blu i{color:#38bdf8;}
.sg-legal{display:flex;gap:14px;}
.sg-legal a{font-size:11.5px;color: #eff1f4;text-decoration:none;transition:.2s;}
.sg-legal a:hover{color: #12fd0e;}

/* BACK TO TOP */
.back-top{position:fixed;bottom:24px;right:24px;width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;font-size:15px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.3s;box-shadow:0 6px 20px rgba(234,88,12,0.4);z-index:999;opacity:0;pointer-events:none;text-decoration:none;}
.back-top.show{opacity:1;pointer-events:all;}
.back-top:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(234,88,12,0.5);}

/* RESPONSIVE */
@media(max-width:900px){.sg-grid{grid-template-columns:1fr 1fr;gap:28px;}}
@media(max-width:560px){.sg-grid{grid-template-columns:1fr;gap:24px;}.sg-bottom{flex-direction:column;align-items:flex-start;}.sports-bar{gap:6px;}}
</style>

<footer class="sg-footer">
  <div class="sg-inner">
    <div class="sg-grid">

      <!-- BRAND -->
      <div>
        <a href="../publics/index.php" class="brand-logo">
          <img src="logo.png" alt="SportGhar">
          <div class="brand-logo-txt">
            <span class="brand-name">SportGhar</span>
            <span class="brand-tag">Nepal's Sport Store</span>
          </div>
        </a>
        <p class="brand-desc">Nepal ko sabai sports ko ek matra destination. Jerseys, shoes, equipment, accessories — sab kuch ek thau bata, genuine quality ma.</p>

        <div class="brand-stats">
          <div class="bstat"><div class="bstat-num">15+</div><div class="bstat-lbl">Sports</div></div>
          <div class="bstat"><div class="bstat-num">500+</div><div class="bstat-lbl">Products</div></div>
          <div class="bstat"><div class="bstat-num">10K+</div><div class="bstat-lbl">Customers</div></div>
        </div>

        <div class="social-label">Follow Us</div>
        <div class="social-row">
          <a href="#" class="sbtn fb" title="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="sbtn ig" title="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" class="sbtn yt" title="YouTube"><i class="fab fa-youtube"></i></a>
          <a href="#" class="sbtn tw" title="Twitter/X"><i class="fab fa-x-twitter"></i></a>
          <a href="#" class="sbtn tk" title="TikTok"><i class="fab fa-tiktok"></i></a>
          <a href="#" class="sbtn wa" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
        </div>

        <div class="nl-box">
          <strong><i class="fa fa-bell" style="margin-right:5px;color:#fb923c;"></i> Stay Updated</strong>
          <p>Exclusive deals, new arrivals & sports news inbox ma paunu hos.</p>
          <div class="nl-form">
            <input type="email" id="nlEmail" placeholder="your@email.com">
            <button onclick="subNL()"><i class="fa fa-paper-plane"></i> Subscribe</button>
          </div>
          <div id="nlMsg" class="nl-msg"></div>
        </div>
      </div>

      <!-- QUICK LINKS -->
      <div class="fc">
        <h4>Quick Links</h4>
        <ul class="fl">
          <li><a href="../publics/index.php"><i class="fa fa-chevron-right"></i> Home</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> All Products</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Jerseys & Kits</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Sports Shoes</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Equipment</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Accessories</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> New Arrivals</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Sale & Offers</a></li>
          <li><a href="../publics/chart.php"><i class="fa fa-chevron-right"></i> My Cart</a></li>
        </ul>
      </div>

      <!-- SUPPORT -->
      <div class="fc">
        <h4>Customer Support</h4>
        <ul class="fl">
          <li><a href="#"><i class="fa fa-chevron-right"></i> Track My Order</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Return & Exchange</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Size Guide</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Payment Methods</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Shipping Info</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Bulk Order</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> FAQ</a></li>
          <li><a href="#"><i class="fa fa-chevron-right"></i> Contact Us</a></li>
        </ul>

        <div style="margin-top:20px;">
          <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:9px;">We Accept</div>
          <div class="pay-row">
            <div class="pay-pill"><i class="fa fa-mobile"></i> eSewa</div>
            <div class="pay-pill"><i class="fa fa-mobile"></i> Khalti</div>
            <div class="pay-pill"><i class="fa fa-money-bill"></i> COD</div>
            <div class="pay-pill"><i class="fa fa-university"></i> Bank</div>
          </div>
        </div>
      </div>

      <!-- CONTACT -->
      <div class="fc">
        <h4>Get In Touch</h4>
        <div class="ci">
          <div class="citem">
            <div class="cicon"><i class="fa fa-location-dot"></i></div>
            <div class="ctxt"><strong>Our Location</strong>Thamel, Kathmandu<br>Bagmati Province, Nepal</div>
          </div>
          <div class="citem">
            <div class="cicon"><i class="fa fa-phone"></i></div>
            <div class="ctxt"><strong>Phone / WhatsApp</strong>+977 98XXXXXXXX<br>+977 98XXXXXXXX</div>
          </div>
          <div class="citem">
            <div class="cicon"><i class="fa fa-envelope"></i></div>
            <div class="ctxt"><strong>Email Us</strong>info@sportghar.com<br>support@sportghar.com</div>
          </div>
          <div class="citem">
            <div class="cicon"><i class="fa fa-clock"></i></div>
            <div class="ctxt"><strong>Business Hours</strong>Sun – Fri: 9:00 AM – 7:00 PM<br>Saturday: 10:00 AM – 5:00 PM</div>
          </div>
          <div class="citem">
            <div class="cicon"><i class="fa fa-store"></i></div>
            <div class="ctxt"><strong>Visit Our Store</strong>Walk-in welcome<br>Free fitting & consultation</div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="sg-divider"></div>

  <div class="sg-bottom">
    <div class="sg-copy">&copy; <?php echo date('Y'); ?> <span>SportGhar</span>. All rights reserved. Made with <i class="fa fa-heart" style="color:#ef4444;font-size:10px;"></i> in Nepal</div>
    <div class="sg-badges">
      <div class="sg-badge grn"><i class="fa fa-shield-halved"></i> Secure Checkout</div>
      <div class="sg-badge org"><i class="fa fa-truck-fast"></i> Fast Delivery</div>
      <div class="sg-badge blu"><i class="fa fa-check-circle"></i> Genuine Products</div>
    </div>
    <div class="sg-legal">
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Use</a>
      <a href="#">Refund Policy</a>
    </div>
  </div>
</footer>

<!-- BACK TO TOP -->
<a class="back-top" id="backTop" href="#" title="Back to top">
  <i class="fa fa-arrow-up"></i>
</a>

<script>
window.addEventListener("scroll",()=>{
  const b=document.getElementById("backTop");
  if(window.scrollY>300)b.classList.add("show");
  else b.classList.remove("show");
});
document.getElementById("backTop").addEventListener("click",e=>{
  e.preventDefault();
  window.scrollTo({top:0,behavior:"smooth"});
});
function subNL(){
  var e=document.getElementById("nlEmail").value.trim();
  var m=document.getElementById("nlMsg");
  if(!e||!e.includes("@")){m.style.color="#ef4444";m.innerHTML='&#10007; Valid email halunus.';return;}
  m.style.color="#22c55e";m.innerHTML='&#10003; Subscribed! Dhanyabad.';
  document.getElementById("nlEmail").value="";
}
</script>