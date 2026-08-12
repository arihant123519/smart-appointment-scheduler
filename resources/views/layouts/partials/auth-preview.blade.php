{{-- Purely decorative product-preview card for the login/register branding
     panel. Sample names/times are illustrative only (a logged-out visitor
     has no real data to show) — labels match the app's actual, real KPIs
     (fill rate, no-show rate, waitlist) rather than invented metrics. --}}

<style>
  .sas-auth-preview {
    background: #fff;
    border: 1px solid var(--sas-gray-100);
    border-radius: var(--sas-radius-lg);
    box-shadow: var(--sas-shadow-md);
    padding: 1.1rem 1.2rem;
  }
  .sas-auth-preview__head { display: flex; align-items: center; gap: .55rem; font-size: var(--sas-fs-sm); margin-bottom: .9rem; }
  .sas-auth-preview__head-icon {
    width: 26px; height: 26px; border-radius: var(--sas-radius-sm); flex-shrink: 0;
    background: var(--sas-primary-50); color: var(--sas-primary-600);
    display: inline-flex; align-items: center; justify-content: center; font-size: .8rem;
  }
  .sas-auth-preview__avatar { width: 22px; height: 22px; border-radius: 50%; background: var(--sas-primary-100); display: inline-block; }
  .sas-auth-preview__stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: .5rem; margin-bottom: 1rem; }
  .sas-auth-preview__stats > div { display: flex; flex-direction: column; gap: .1rem; }
  .sas-auth-preview__stat-label { font-size: .68rem; color: var(--sas-gray-400); }
  .sas-auth-preview__stat-value { font-size: .95rem; font-weight: 700; color: var(--sas-gray-900); }
  .sas-auth-preview__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; }
  .sas-auth-preview__col { display: flex; flex-direction: column; gap: .35rem; }
  .sas-auth-preview__block {
    font-size: .64rem; font-weight: 600; border-radius: var(--sas-radius-sm);
    padding: .3rem .4rem; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
</style>
