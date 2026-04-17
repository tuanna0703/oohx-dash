{{-- Shared map marker + cluster styles. Must be included inside a @push('head') style block. --}}
<style>
/* ── Pin base ── */
.oohx-pin{background:none;border:none}

/* Tier 1: Dot (zoom < 14) */
.oohx-dot{width:12px;height:12px;border-radius:50%;border:2px solid rgba(255,255,255,.9);box-shadow:0 2px 8px rgba(0,0,0,.25);cursor:pointer;transition:transform 160ms}
.oohx-dot:hover{transform:scale(1.4)}
.oohx-dot--bl{background:var(--bl)}.oohx-dot--grn{background:var(--grn)}.oohx-dot--org{background:var(--org)}.oohx-dot--red{background:var(--red)}

/* Tier 2: Pill (zoom 14-15) — icon + network name */
.oohx-pin-box{border-radius:10px;padding:5px 10px;font-size:11px;font-weight:700;color:#fff;display:flex;align-items:center;gap:4px;white-space:nowrap;box-shadow:0 4px 16px rgba(0,0,0,.2);cursor:pointer;transition:transform 200ms var(--spring);max-width:160px;overflow:hidden}
.oohx-pin-box:hover{transform:scale(1.08)}
.oohx-pin-box svg{width:14px;height:14px;flex-shrink:0}
.oohx-pin-box span{overflow:hidden;text-overflow:ellipsis}
.oohx-pin-arrow{width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;margin:-1px auto 0}
.oohx-pin--bl .oohx-pin-box{background:var(--bl)}.oohx-pin--bl .oohx-pin-arrow{border-top:8px solid var(--bl)}
.oohx-pin--grn .oohx-pin-box{background:var(--grn)}.oohx-pin--grn .oohx-pin-arrow{border-top:8px solid var(--grn)}
.oohx-pin--org .oohx-pin-box{background:var(--org)}.oohx-pin--org .oohx-pin-arrow{border-top:8px solid var(--org)}
.oohx-pin--red .oohx-pin-box{background:var(--red)}.oohx-pin--red .oohx-pin-arrow{border-top:8px solid var(--red)}

/* Tier 3: Rich (zoom >= 16) — logo + name + meta */
.oohx-rich{display:flex;align-items:center;gap:8px;background:#fff;border-radius:12px;padding:6px 10px 6px 6px;box-shadow:0 4px 20px rgba(0,0,0,.18);cursor:pointer;transition:transform 200ms var(--spring);border:1px solid var(--ln2);max-width:200px}
.oohx-rich:hover{transform:scale(1.05);box-shadow:0 6px 24px rgba(0,0,0,.22)}
.oohx-rich-logo{width:32px;height:32px;border-radius:8px;object-fit:contain;background:#fff;flex-shrink:0;border:1px solid var(--ln2);padding:2px}
.oohx-rich-initials{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.oohx-rich-initials--bl{background:rgba(42,79,246,.1);color:var(--bl)}
.oohx-rich-initials--grn{background:rgba(52,199,89,.1);color:#1a7d37}
.oohx-rich-initials--org{background:rgba(255,159,10,.1);color:#c93400}
.oohx-rich-initials--red{background:rgba(255,59,48,.1);color:#d70015}
.oohx-rich-body{min-width:0;flex:1}
.oohx-rich-name{font-size:12px;font-weight:700;color:var(--t1);line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.oohx-rich-meta{font-size:10px;color:var(--t4);line-height:1.2;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.oohx-rich--bl{border-left:3px solid var(--bl)}.oohx-rich--grn{border-left:3px solid var(--grn)}
.oohx-rich--org{border-left:3px solid var(--org)}.oohx-rich--red{border-left:3px solid var(--red)}
.oohx-rich-badge{min-width:22px;height:22px;padding:0 6px;border-radius:11px;background:var(--bl);color:#fff;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 6px rgba(42,79,246,.3);margin-left:auto}

/* ── Cluster circles ── */
.marker-cluster{background:none !important;border:none !important}
.oohx-cluster{display:flex;align-items:center;justify-content:center;border-radius:50%;color:#fff;font-weight:700;box-shadow:0 4px 20px rgba(42,79,246,.35);cursor:pointer;transition:transform 200ms ease;position:relative}
.oohx-cluster:hover{transform:scale(1.12)}
.oohx-cluster--sm{width:44px;height:44px;font-size:13px;background:var(--bl);border:3px solid rgba(255,255,255,.9)}
.oohx-cluster--md{width:56px;height:56px;font-size:14px;background:var(--bl);border:3px solid rgba(255,255,255,.9)}
.oohx-cluster--lg{width:68px;height:68px;font-size:15px;background:#1a3af0;border:4px solid rgba(255,255,255,.9)}
.oohx-cluster--xl{width:80px;height:80px;font-size:16px;background:#0f28c7;border:4px solid rgba(255,255,255,.9)}
.oohx-cluster::after{content:'';position:absolute;inset:-6px;border-radius:50%;background:rgba(42,79,246,.15);z-index:-1;animation:cluster-pulse 2s ease-in-out infinite}
@keyframes cluster-pulse{0%,100%{transform:scale(1);opacity:.6}50%{transform:scale(1.15);opacity:.2}}
</style>
